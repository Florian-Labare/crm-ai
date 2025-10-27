import React, { useState } from "react";
import AudioRecorder from "../components/AudioRecorder";
import RecordingsList from "../components/RecordingsList";
import type { AudioResponse } from "../types/api"; // ✅ type-only

const Dashboard: React.FC = () => {
  const [lastUpload, setLastUpload] = useState<AudioResponse | null>(null);

  return (
    <div className="p-6">
      <h1>🎙 Gestion des enregistrements vocaux</h1>
      <AudioRecorder onUploadSuccess={setLastUpload} />
      {lastUpload && (
        <div className="mt-4 p-2 border bg-green-50">
          <p><strong>Client créé / mis à jour :</strong></p>
          <pre>{JSON.stringify(lastUpload.client, null, 2)}</pre>
        </div>
      )}
      <RecordingsList />
    </div>
  );
};

export default Dashboard;
