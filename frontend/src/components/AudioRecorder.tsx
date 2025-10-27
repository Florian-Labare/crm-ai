import React, { useState, useRef } from "react";
import api from "../api/apiClient";
import type { AudioResponse } from "../types/api"; // ✅ import type séparé

interface AudioRecorderProps {
  onUploadSuccess: (data: AudioResponse) => void;
}

const AudioRecorder: React.FC<AudioRecorderProps> = ({ onUploadSuccess }) => {
  const [isRecording, setIsRecording] = useState(false);
  const [status, setStatus] = useState("");
  const [mediaRecorder, setMediaRecorder] = useState<MediaRecorder | null>(null);
  const audioChunks = useRef<Blob[]>([]);

  const startRecording = async () => {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const recorder = new MediaRecorder(stream);

    recorder.ondataavailable = e => audioChunks.current.push(e.data);

    recorder.onstop = async () => {
      const blob = new Blob(audioChunks.current, { type: "audio/webm" });
      audioChunks.current = [];

      const formData = new FormData();
      formData.append("audio", blob, "recording.webm");

      setStatus("⏳ Envoi en cours…");

      try {
        const res = await api.post<AudioResponse>("/audio/upload", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        setStatus("✅ Audio traité !");
        onUploadSuccess(res.data);
      } catch (error: any) {
        setStatus("❌ Erreur : " + (error.response?.data?.error || error.message));
      }
    };

    recorder.start();
    setMediaRecorder(recorder);
    setIsRecording(true);
    setStatus("🎙 Enregistrement…");
  };

  const stopRecording = () => {
    mediaRecorder?.stop();
    setIsRecording(false);
  };

  return (
    <div className="p-4 border rounded shadow-sm">
      <p>{status}</p>
      {!isRecording ? (
        <button onClick={startRecording}>🎙 Démarrer l’enregistrement</button>
      ) : (
        <button onClick={stopRecording}>⏹ Stop</button>
      )}
    </div>
  );
};

export default AudioRecorder;
