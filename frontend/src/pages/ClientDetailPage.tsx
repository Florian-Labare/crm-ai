import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../api/apiClient";
import AudioRecorder from "../components/AudioRecorder";

interface Client {
  id: number;
  nom: string;
  prenom: string;
  situationmatrimoniale?: string;
  profession?: string;
  besoins?: string;
}

const ClientDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const [client, setClient] = useState<Client | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchClient = async () => {
    try {
      const res = await api.get(`/clients/${id}`);
      setClient(res.data);
    } catch (err) {
      console.error("Erreur lors du chargement du client :", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchClient();
  }, [id]);

  if (loading) return <div className="text-center mt-10">Chargement...</div>;
  if (!client) return <div className="text-center mt-10">Client introuvable.</div>;

  return (
    <div className="p-6 max-w-3xl mx-auto bg-white shadow-md rounded-lg">
      <h1 className="text-2xl font-bold mb-4">
        {client.prenom} {client.nom}
      </h1>

      <div className="space-y-2 mb-4">
        <p>
          💍 <strong>Situation :</strong>{" "}
          {client.situationmatrimoniale || "Non renseigné"}
        </p>
        <p>
          💼 <strong>Profession :</strong> {client.profession || "Non renseignée"}
        </p>
        <p>
          🎯 <strong>Besoins :</strong> {client.besoins || "Non renseignés"}
        </p>
      </div>

      <hr className="my-4" />

      <AudioRecorder
        clientId={client.id}
        onUpdateClient={(updatedClient) => setClient(updatedClient)} // 🔁 MAJ après traitement audio
      />
    </div>
  );
};

export default ClientDetailPage;
