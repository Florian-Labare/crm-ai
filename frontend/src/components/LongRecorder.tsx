import React, { useState, useRef, useEffect } from 'react';
import { Mic, Square, Loader2 } from 'lucide-react';
import { v4 as uuidv4 } from 'uuid';
import axios from 'axios';
import { toast } from 'react-toastify';

interface LongRecorderProps {
  clientId?: number;
  onTranscriptionComplete: (transcription: string) => void;
}

const CHUNK_DURATION = 600000; // 10 minutes en millisecondes
const API_BASE_URL = 'http://localhost:8000/api';

export const LongRecorder: React.FC<LongRecorderProps> = ({
  clientId,
  onTranscriptionComplete,
}) => {
  const [isRecording, setIsRecording] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [processingStatus, setProcessingStatus] = useState<string>('');
  const [sessionId] = useState(() => uuidv4());
  const [partIndex, setPartIndex] = useState(0);
  const [recordingTime, setRecordingTime] = useState(0);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);
  const chunkIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const timerIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const pollingIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Formater le temps d'enregistrement
  const formatTime = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  // Démarrer le timer
  const startTimer = () => {
    timerIntervalRef.current = setInterval(() => {
      setRecordingTime((prev) => prev + 1);
    }, 1000);
  };

  // Arrêter le timer
  const stopTimer = () => {
    if (timerIntervalRef.current) {
      clearInterval(timerIntervalRef.current);
      timerIntervalRef.current = null;
    }
  };

  // Polling pour vérifier le statut du traitement GPT
  const startPolling = (audioRecordId: number) => {
    console.log(`🔄 Démarrage du polling pour audio_record #${audioRecordId}`);
    setProcessingStatus('⏳ En attente de traitement IA...');

    // Vérifier immédiatement
    checkStatus(audioRecordId);

    // Puis toutes les 2 secondes
    pollingIntervalRef.current = setInterval(() => {
      checkStatus(audioRecordId);
    }, 2000);
  };

  // Vérifier le statut d'un enregistrement
  const checkStatus = async (audioRecordId: number) => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/audio/status/${audioRecordId}`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      const { data: audioRecord } = response.data;
      const { status, client, error: errorMsg } = audioRecord;

      console.log(`📊 Statut audio #${audioRecordId}: ${status}`);

      // Mettre à jour le message de statut
      switch (status) {
        case 'pending':
          setProcessingStatus('⏳ En attente de traitement...');
          break;
        case 'processing':
          setProcessingStatus('🧠 Analyse IA en cours...');
          break;
        case 'done':
          setProcessingStatus('✅ Traitement terminé !');
          stopPolling();
          handleSuccess(client);
          break;
        case 'failed':
          setProcessingStatus('');
          stopPolling();
          handleError(errorMsg || 'Le traitement a échoué');
          break;
      }
    } catch (err) {
      console.error('Erreur lors de la vérification du statut:', err);
    }
  };

  // Arrêter le polling
  const stopPolling = () => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    setIsProcessing(false);
  };

  // Gérer le succès du traitement
  const handleSuccess = (client: any) => {
    console.log("✅ Client mis à jour :", client);

    const clientName = client.prenom && client.nom
      ? `${client.prenom} ${client.nom}`
      : "Client";

    if (clientId) {
      toast.success(`✅ Fiche client "${clientName}" mise à jour !`);
    } else {
      toast.success(`✅ Fiche client "${clientName}" créée !`);
    }

    onTranscriptionComplete('');
  };

  // Gérer les erreurs
  const handleError = (errorMsg: string) => {
    console.error(errorMsg);
    toast.error(`❌ ${errorMsg}`);
  };

  // Envoyer un chunk au backend
  const uploadChunk = async (audioBlob: Blob, index: number) => {
    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('part_index', index.toString());
    formData.append('audio', audioBlob, `chunk_${index}.webm`);
    if (clientId) {
      formData.append('client_id', clientId.toString());
    }

    try {
      const token = localStorage.getItem('token');
      await axios.post(`${API_BASE_URL}/recordings/chunk`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
          Authorization: `Bearer ${token}`,
        },
      });
      console.log(`✅ Chunk #${index} envoyé avec succès`);
    } catch (error) {
      console.error(`❌ Erreur lors de l'envoi du chunk #${index}:`, error);
      throw error;
    }
  };

  // Créer un chunk et l'envoyer
  const createAndUploadChunk = async (currentPartIndex: number) => {
    if (chunksRef.current.length === 0) return;

    const audioBlob = new Blob(chunksRef.current, { type: 'audio/webm' });
    chunksRef.current = []; // Vider le buffer

    await uploadChunk(audioBlob, currentPartIndex);
  };

  // Démarrer l'enregistrement
  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream, {
        mimeType: 'audio/webm',
      });

      mediaRecorderRef.current = mediaRecorder;
      chunksRef.current = [];

      // Collecter les données audio
      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          chunksRef.current.push(event.data);
        }
      };

      // Démarrer l'enregistrement
      mediaRecorder.start();
      setIsRecording(true);
      setPartIndex(0);
      setRecordingTime(0);
      startTimer();

      console.log(`🎙️ Enregistrement démarré - Session: ${sessionId}`);

      // Créer et envoyer un chunk toutes les 10 minutes
      chunkIntervalRef.current = setInterval(async () => {
        if (mediaRecorderRef.current && mediaRecorderRef.current.state === 'recording') {
          // Arrêter temporairement pour récupérer les données
          mediaRecorderRef.current.stop();

          // Attendre que les données soient disponibles
          await new Promise((resolve) => setTimeout(resolve, 100));

          // Créer et envoyer le chunk
          const currentIndex = partIndex;
          await createAndUploadChunk(currentIndex);
          setPartIndex((prev) => prev + 1);

          // Redémarrer l'enregistrement pour le chunk suivant
          if (stream.active) {
            const newMediaRecorder = new MediaRecorder(stream, {
              mimeType: 'audio/webm',
            });
            newMediaRecorder.ondataavailable = (event) => {
              if (event.data.size > 0) {
                chunksRef.current.push(event.data);
              }
            };
            newMediaRecorder.start();
            mediaRecorderRef.current = newMediaRecorder;
          }
        }
      }, CHUNK_DURATION);
    } catch (error) {
      console.error('❌ Erreur lors du démarrage de l\'enregistrement:', error);
      alert('Impossible d\'accéder au microphone. Veuillez vérifier les permissions.');
    }
  };

  // Arrêter l'enregistrement
  const stopRecording = async () => {
    if (!mediaRecorderRef.current) return;

    stopTimer();
    setIsProcessing(true);

    // Arrêter l'intervalle de chunks
    if (chunkIntervalRef.current) {
      clearInterval(chunkIntervalRef.current);
      chunkIntervalRef.current = null;
    }

    // Arrêter le MediaRecorder
    mediaRecorderRef.current.stop();

    // Attendre que les dernières données soient disponibles
    await new Promise((resolve) => setTimeout(resolve, 100));

    // Envoyer le dernier chunk
    const lastIndex = partIndex;
    await createAndUploadChunk(lastIndex);

    // Arrêter le stream
    if (mediaRecorderRef.current.stream) {
      mediaRecorderRef.current.stream.getTracks().forEach((track) => track.stop());
    }

    setIsRecording(false);

    console.log(`🎬 Enregistrement arrêté - Finalisation en cours...`);

    // Finaliser l'enregistrement
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(
        `${API_BASE_URL}/recordings/${sessionId}/finalize`,
        {},
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      );

      const { audio_record_id, transcription } = response.data;
      console.log(`✅ Transcription reçue: ${transcription.substring(0, 100)}...`);
      console.log(`📝 AudioRecord créé: #${audio_record_id}`);

      // Démarrer le polling pour le traitement GPT
      if (audio_record_id) {
        startPolling(audio_record_id);
      } else {
        throw new Error('Pas d\'ID d\'enregistrement retourné');
      }
    } catch (error) {
      console.error('❌ Erreur lors de la finalisation:', error);
      toast.error('Erreur lors de la finalisation de l\'enregistrement.');
      setIsProcessing(false);
    } finally {
      setRecordingTime(0);
    }
  };

  // Nettoyage lors du démontage
  useEffect(() => {
    return () => {
      if (chunkIntervalRef.current) {
        clearInterval(chunkIntervalRef.current);
      }
      if (timerIntervalRef.current) {
        clearInterval(timerIntervalRef.current);
      }
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
      }
      if (mediaRecorderRef.current && mediaRecorderRef.current.state === 'recording') {
        mediaRecorderRef.current.stop();
        if (mediaRecorderRef.current.stream) {
          mediaRecorderRef.current.stream.getTracks().forEach((track) => track.stop());
        }
      }
    };
  }, []);

  return (
    <div className="flex flex-col items-center gap-4 p-6 bg-white rounded-lg shadow-md">
      <div className="text-center">
        <h3 className="text-lg font-semibold text-gray-800 mb-2">
          Enregistrement Long (jusqu'à 2h)
        </h3>
        {isRecording && (
          <div className="text-2xl font-mono text-blue-600">
            {formatTime(recordingTime)}
          </div>
        )}
        {!isRecording && !isProcessing && (
          <p className="text-sm text-gray-600">
            Enregistrement automatique par segments de 10 minutes
          </p>
        )}
        {isProcessing && (
          <p className="text-sm text-blue-600 flex items-center justify-center gap-2">
            <Loader2 className="animate-spin" size={16} />
            {processingStatus || 'Finalisation et transcription en cours...'}
          </p>
        )}
      </div>

      <button
        onClick={isRecording ? stopRecording : startRecording}
        disabled={isProcessing}
        className={`flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all ${
          isRecording
            ? 'bg-red-500 hover:bg-red-600'
            : isProcessing
            ? 'bg-gray-400 cursor-not-allowed'
            : 'bg-blue-500 hover:bg-blue-600'
        }`}
      >
        {isProcessing ? (
          <>
            <Loader2 className="animate-spin" size={20} />
            Traitement...
          </>
        ) : isRecording ? (
          <>
            <Square size={20} />
            Arrêter
          </>
        ) : (
          <>
            <Mic size={20} />
            Démarrer
          </>
        )}
      </button>

      {isRecording && (
        <div className="text-xs text-gray-500 text-center">
          Session: {sessionId.substring(0, 8)}... | Chunk: {partIndex + 1}
        </div>
      )}
    </div>
  );
};
