import React, { useEffect, useState } from "react";
import api from "../api/apiClient";
import { useNavigate } from "react-router-dom";

interface Client {
  id: number;
  nom: string;
  prenom: string;
  profession?: string;
}

const ClientsPage: React.FC = () => {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchClients();
  }, []);

  const fetchClients = async () => {
    try {
      setLoading(true);
      const res = await api.get("/clients");
      setClients(res.data);
    } catch (err) {
      setError("Erreur lors du chargement des clients.");
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Voulez-vous vraiment supprimer ce client ?")) return;

    try {
      await api.delete(`/clients/${id}`);
      setClients((prev) => prev.filter((c) => c.id !== id)); // ✅ mise à jour immédiate
    } catch (err) {
      alert("❌ Erreur lors de la suppression du client.");
      console.error(err);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center mt-10 text-gray-600">
        Chargement des clients...
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-red-600 text-center mt-10">
        ❌ {error}
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-md">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">👥 Liste des clients</h1>
        <button
          onClick={() => navigate("/clients/new")}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-all"
        >
          + Nouveau client
        </button>
      </div>

      {clients.length === 0 ? (
        <p className="text-gray-500 text-center py-8">
          Aucun client pour le moment.
        </p>
      ) : (
        <ul className="divide-y divide-gray-200">
          {clients.map((client) => (
            <li
              key={client.id}
              className="flex justify-between items-center py-4 hover:bg-gray-50 transition-colors"
            >
              <div
                onClick={() => navigate(`/clients/${client.id}`)}
                className="cursor-pointer"
              >
                <p className="font-semibold text-gray-800">
                  {client.prenom} {client.nom.toUpperCase()}
                </p>
                <p className="text-sm text-gray-500">
                  {client.profession || "Profession non renseignée"}
                </p>
              </div>

              <button
                onClick={(e) => {
                  e.stopPropagation();
                  handleDelete(client.id);
                }}
                className="text-red-600 hover:text-red-800 transition-colors"
              >
                🗑️ Supprimer
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default ClientsPage;
