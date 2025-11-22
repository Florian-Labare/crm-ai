import React, { useState, useEffect, useRef } from "react";
import { toast } from "react-toastify";
import api from "../api/apiClient";
import RecordRTC from "recordrtc";

interface Props {
  clientId?: number;
  onUpdateClient?: (data: any) => void;
  onUploadSuccess?: (data: any) => void;
}

const AudioRecorder: React.FC<Props> = ({ clientId, onUpdateClient, onUploadSuccess }) => {
  const [recording, setRecording] = useState(false);
  const [recorder, setRecorder] = useState<RecordRTC | null>(null);
  const [loading, setLoading] = useState(false);
  const [processingStatus, setProcessingStatus] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [recordingDuration, setRecordingDuration] = useState<number>(0);

  const pollingIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const audioRecordIdRef = useRef<number | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const durationIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // Nettoyage du polling et du timer au démontage du composant
  useEffect(() => {
    return () => {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
      }
      if (durationIntervalRef.current) {
        clearInterval(durationIntervalRef.current);
      }
    };
  }, []);

  /**
   * Polling pour vérifier le statut du traitement
   */
  const startPolling = (audioRecordId: number) => {
    audioRecordIdRef.current = audioRecordId;

    // Vérifier immédiatement
    checkStatus(audioRecordId);

    // Puis toutes les 2 secondes
    pollingIntervalRef.current = setInterval(() => {
      checkStatus(audioRecordId);
    }, 2000);
  };

  /**
   * Vérifier le statut d'un enregistrement
   */
  const checkStatus = async (audioRecordId: number) => {
    try {
      const res = await api.get(`/audio/status/${audioRecordId}`);
      const { status, client, error: errorMsg } = res.data;

      console.log(`📊 Statut audio #${audioRecordId}: ${status}`);

      // Mettre à jour le message de statut
      switch (status) {
        case 'pending':
          setProcessingStatus('⏳ En attente de traitement...');
          break;
        case 'processing':
          setProcessingStatus('🧠 Transcription et analyse IA en cours...');
          break;
        case 'done':
          // ✅ Traitement terminé avec succès
          setProcessingStatus('✅ Traitement terminé !');
          handleSuccess(client);
          stopPolling();
          break;
        case 'failed':
          // ❌ Traitement échoué
          setProcessingStatus('');
          handleError(errorMsg || 'Le traitement a échoué');
          stopPolling();
          break;
      }
    } catch (err) {
      console.error('Erreur lors de la vérification du statut:', err);
      // Ne pas arrêter le polling pour une erreur réseau temporaire
    }
  };

  /**
   * Arrêter le polling
   */
  const stopPolling = () => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    setLoading(false);
    audioRecordIdRef.current = null;
  };

  /**
   * Gérer le succès du traitement
   */
  const handleSuccess = (client: any) => {
    console.log("✅ Client mis à jour :", client);

    // Afficher un toast de succès avec le nom du client
    const clientName = client.prenom && client.nom
      ? `${client.prenom} ${client.nom}`
      : "Client";

    if (clientId) {
      toast.success(`✅ Fiche client "${clientName}" mise à jour !`);
    } else {
      toast.success(`✅ Fiche client "${clientName}" créée !`);
    }

    if (onUpdateClient) {
      onUpdateClient(client); // 🧠 mise à jour en direct du parent
    }
    if (onUploadSuccess) {
      onUploadSuccess({ client }); // 🧠 callback pour Dashboard
    }
    setError(null);
  };

  /**
   * Gérer les erreurs
   */
  const handleError = (errorMsg: string) => {
    console.error(errorMsg);
    setError(errorMsg);
    toast.error(`❌ ${errorMsg}`);
  };

  const startRecording = async () => {
    try {
      setError(null);
      setRecordingDuration(0);

      const stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          sampleRate: 44100,
        }
      });

      streamRef.current = stream;

      const rtcRecorder = new RecordRTC(stream, {
        type: 'audio',
        mimeType: 'audio/wav',
        recorderType: RecordRTC.StereoAudioRecorder,
        numberOfAudioChannels: 1,
        desiredSampRate: 16000,
        timeSlice: 1000,
        bufferSize: 16384,
      });

      rtcRecorder.startRecording();
      setRecorder(rtcRecorder);
      setRecording(true);

      // Timer de durée d'enregistrement
      const startTime = Date.now();
      durationIntervalRef.current = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        setRecordingDuration(elapsed);

        // Limite de 10 minutes (600 secondes)
        if (elapsed >= 600) {
          toast.warning('⏱️ Durée maximale atteinte (10 minutes). Arrêt automatique...');
          stopRecording();
        }
      }, 1000);

      console.log('🎙️ Enregistrement démarré avec RecordRTC (format WAV optimisé)');
    } catch (err) {
      console.error(err);
      const errorMsg = "Impossible d'accéder au micro. Veuillez autoriser l'accès au microphone.";
      setError(errorMsg);
      toast.error(errorMsg);
    }
  };

  const stopRecording = () => {
    if (recorder && recording) {
      // Arrêter le timer
      if (durationIntervalRef.current) {
        clearInterval(durationIntervalRef.current);
        durationIntervalRef.current = null;
      }

      recorder.stopRecording(async () => {
        const blob = recorder.getBlob();

        if (streamRef.current) {
          streamRef.current.getTracks().forEach(track => track.stop());
          streamRef.current = null;
        }

        // Vérifier la taille du fichier
        const fileSizeMB = blob.size / (1024 * 1024);
        console.log(`📊 Taille du fichier audio: ${fileSizeMB.toFixed(2)} MB`);

        if (fileSizeMB > 40) {
          const errorMsg = `Le fichier audio est trop volumineux (${fileSizeMB.toFixed(1)} MB). Veuillez enregistrer un message plus court.`;
          handleError(errorMsg);
          setRecording(false);
          setRecorder(null);
          setRecordingDuration(0);
          return;
        }

        if (fileSizeMB > 20) {
          toast.warning(`⚠️ Fichier volumineux (${fileSizeMB.toFixed(1)} MB). L'upload peut prendre du temps...`);
        }

        const formData = new FormData();
        formData.append("audio", blob, "recording.wav");
        if (clientId) {
          formData.append("client_id", clientId.toString());
        }

        try {
          setLoading(true);
          setProcessingStatus('📤 Upload de l\'audio...');

          const res = await api.post(`/audio/upload`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });

          if (res.data && res.data.audio_record_id) {
            console.log(`📝 Audio record créé : ID ${res.data.audio_record_id}`);
            startPolling(res.data.audio_record_id);
          } else {
            throw new Error('Pas d\'ID d\'enregistrement retourné');
          }
        } catch (err: any) {
          console.error(err);
          const errorMsg = err.response?.data?.message || "Erreur lors de l'upload de l'audio.";
          handleError(errorMsg);
          setLoading(false);
        }
      });

      setRecording(false);
      setRecorder(null);
    }
  };

  return (
    <div className="flex flex-col items-center space-y-4 mt-6 w-full">
      <h3 className="text-lg font-semibold mb-2">🎙️ Enregistrement vocal</h3>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-md w-full text-center">
          ❌ {error}
        </div>
      )}

      {!recording ? (
        <button
          onClick={startRecording}
          className="bg-green-600 hover:bg-green-700 text-white font-medium px-5 py-2 rounded-lg transition-all disabled:opacity-50"
          disabled={loading}
        >
          🎙️ Démarrer l'enregistrement
        </button>
      ) : (
        <div className="flex flex-col items-center space-y-3">
          <div className="flex items-center space-x-3 text-red-600 font-mono text-xl">
            <span className="animate-pulse">⏺</span>
            <span>
              {Math.floor(recordingDuration / 60)}:{(recordingDuration % 60).toString().padStart(2, '0')}
            </span>
            <span className="text-sm text-gray-500">/ 10:00</span>
          </div>
          <button
            onClick={stopRecording}
            className="bg-red-600 hover:bg-red-700 text-white font-medium px-5 py-2 rounded-lg transition-all"
          >
            ⏹️ Arrêter l'enregistrement
          </button>
        </div>
      )}

      {loading && processingStatus && (
        <div className="flex items-center space-x-2 mt-4 text-gray-600">
          <svg
            className="animate-spin h-5 w-5 text-blue-600"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            ></circle>
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8v8H4z"
            ></path>
          </svg>
          <span>{processingStatus}</span>
        </div>
      )}
    </div>
  );
};

export default AudioRecorder;
