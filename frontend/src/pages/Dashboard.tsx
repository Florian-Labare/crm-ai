import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import AudioRecorder from "../components/AudioRecorder";
import RecordingsList from "../components/RecordingsList";
import type { AudioResponse } from "../types/api"; // ✅ type-only

const Dashboard: React.FC = () => {
  const [lastUpload, setLastUpload] = useState<AudioResponse | null>(null);
  const navigate = useNavigate();

  const handleUploadSuccess = (data: AudioResponse) => {
    setLastUpload(data);

    // Rediriger vers la fiche du client créé/mis à jour après 1.5 secondes
    if (data.client?.id) {
      setTimeout(() => {
        navigate(`/clients/${data.client.id}`);
      }, 1500);
    }
  };

  return (
    <div className="p-6">
      <h1>🎙 Gestion des enregistrements vocaux</h1>
      <AudioRecorder onUploadSuccess={handleUploadSuccess} />
      {lastUpload && (
        <div className="mt-4 p-2 border bg-green-50">
          <p><strong>✅ Client créé / mis à jour avec succès !</strong></p>
          <p className="text-sm mt-2">Redirection vers la fiche client...</p>
        </div>
      )}
      <RecordingsList />
    </div>
  );
};

export default Dashboard;
