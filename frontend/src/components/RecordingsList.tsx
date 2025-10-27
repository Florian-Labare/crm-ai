import React, { useEffect, useState } from "react";
import api from "../api/apiClient";

interface AudioRecord {
  id: number;
  client_id: number | null;
  path: string;
  transcription: string;
}

const RecordingsList: React.FC = () => {
  const [records, setRecords] = useState<AudioRecord[]>([]);

  useEffect(() => {
    api.get<AudioRecord[]>("/recordings").then(res => setRecords(res.data));
  }, []);

  return (
    <div>
      <h3>🎧 Enregistrements</h3>
      {records.map(r => (
        <div key={r.id} className="p-2 border-b">
          <audio controls src={`http://localhost:8000/storage/${r.path}`} />
          <p><strong>Client:</strong> {r.client_id || "Nouveau"}</p>
          <p><em>{r.transcription}</em></p>
        </div>
      ))}
    </div>
  );
};

export default RecordingsList;
