import React, { useState } from "react";
import api from "../api/apiClient";

interface Props {
  clientId: number;
  onUpdateClient: (data: any) => void;
}

const AudioRecorder: React.FC<Props> = ({ clientId, onUpdateClient }) => {
  const [recording, setRecording] = useState(false);
  const [recorder, setRecorder] = useState<MediaRecorder | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const startRecording = async () => {
    try {
      setError(null);
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      const chunks: BlobPart[] = [];

      mediaRecorder.ondataavailable = (e) => chunks.push(e.data);

      mediaRecorder.onstop = async () => {
        const blob = new Blob(chunks, { type: "audio/webm" });
        const formData = new FormData();
        formData.append("audio", blob);
        formData.append("client_id", clientId.toString()); // 🆕 pour rattacher le client

        try {
          setLoading(true);
          const res = await api.post(`/audio/upload`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
            timeout: 120000,
          });

          if (res.data) {
            console.log("✅ Client mis à jour :", res.data);
            onUpdateClient(res.data); // 🧠 mise à jour en direct du parent
          }
        } catch (err) {
          console.error(err);
          setError("Erreur lors du traitement de l’audio.");
        } finally {
          setLoading(false);
        }
      };

      mediaRecorder.start();
      setRecorder(mediaRecorder);
      setRecording(true);
    } catch (err) {
      console.error(err);
      setError("Impossible d'accéder au micro.");
    }
  };

  const stopRecording = () => {
    if (recorder) {
      recorder.stop();
      setRecording(false);
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
          Démarrer l’enregistrement
        </button>
      ) : (
        <button
          onClick={stopRecording}
          className="bg-red-600 hover:bg-red-700 text-white font-medium px-5 py-2 rounded-lg transition-all"
        >
          Arrêter l’enregistrement
        </button>
      )}

      {loading && (
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
          <span>Traitement de l’audio en cours...</span>
        </div>
      )}
    </div>
  );
};

export default AudioRecorder;
