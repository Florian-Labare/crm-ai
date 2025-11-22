import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { ConfirmDialog } from "../components/ConfirmDialog";

interface Client {
  id: number;
  nom: string;
  prenom: string;
  email?: string;
  telephone?: string;
  profession?: string;
  ville?: string;
  situation_matrimoniale?: string;
  besoins?: string[];
  created_at?: string;
}

const HomePage: React.FC = () => {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    total: 0,
    nouveaux: 0,
    avecBesoins: 0,
  });
  const navigate = useNavigate();

  // État pour le dialogue de confirmation
  const [confirmDialog, setConfirmDialog] = useState<{
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
  }>({
    isOpen: false,
    title: '',
    message: '',
    onConfirm: () => {},
  });

  useEffect(() => {
    fetchClients();
  }, []);

  const fetchClients = async () => {
    try {
      setLoading(true);
      const res = await api.get("/clients");
      setClients(res.data);

      // Calculer les statistiques
      const total = res.data.length;
      const nouveaux = res.data.filter((c: Client) => {
        if (!c.created_at) return false;
        const createdDate = new Date(c.created_at);
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        return createdDate > weekAgo;
      }).length;
      const avecBesoins = res.data.filter(
        (c: Client) => c.besoins && c.besoins.length > 0
      ).length;

      setStats({ total, nouveaux, avecBesoins });
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement des clients");
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (id: number, nom: string, prenom: string) => {
    setConfirmDialog({
      isOpen: true,
      title: 'Supprimer le client',
      message: `Êtes-vous sûr de vouloir supprimer ${prenom} ${nom} ? Cette action est irréversible.`,
      onConfirm: async () => {
        try {
          await api.delete(`/clients/${id}`);
          setClients((prev) => prev.filter((c) => c.id !== id));
          toast.success("Client supprimé avec succès");

          // Recalculer les stats
          setStats((prev) => ({ ...prev, total: prev.total - 1 }));
        } catch (err) {
          console.error(err);
          toast.error("Erreur lors de la suppression du client");
        }
      },
    });
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen">
        <div className="flex flex-col items-center space-y-4">
          <svg
            className="animate-spin h-12 w-12 text-indigo-600"
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
          <p className="text-gray-600 font-medium">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        {/* Header avec gradient */}
        <div className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-12 shadow-xl">
          <div className="max-w-7xl mx-auto">
            <h1 className="text-4xl font-bold mb-2">Tableau de bord</h1>
            <p className="text-indigo-100 text-lg">
              Gérez vos clients et leurs besoins en un clin d'œil
            </p>
          </div>
        </div>

        {/* Statistiques */}
        <div className="max-w-7xl mx-auto px-8 -mt-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500 transform hover:scale-105 transition-transform">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-gray-500 text-sm font-medium uppercase">
                    Total Clients
                  </p>
                  <p className="text-3xl font-bold text-gray-800 mt-2">
                    {stats.total}
                  </p>
                </div>
                <div className="bg-indigo-100 rounded-full p-4">
                  <svg
                    className="w-8 h-8 text-indigo-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                  </svg>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 transform hover:scale-105 transition-transform">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-gray-500 text-sm font-medium uppercase">
                    Nouveaux (7j)
                  </p>
                  <p className="text-3xl font-bold text-gray-800 mt-2">
                    {stats.nouveaux}
                  </p>
                </div>
                <div className="bg-green-100 rounded-full p-4">
                  <svg
                    className="w-8 h-8 text-green-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                    />
                  </svg>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500 transform hover:scale-105 transition-transform">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-gray-500 text-sm font-medium uppercase">
                    Avec besoins
                  </p>
                  <p className="text-3xl font-bold text-gray-800 mt-2">
                    {stats.avecBesoins}
                  </p>
                </div>
                <div className="bg-purple-100 rounded-full p-4">
                  <svg
                    className="w-8 h-8 text-purple-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                    />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          {/* Actions rapides */}
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-2xl font-bold text-gray-800">Mes clients</h2>
            <button
              onClick={() => navigate("/clients/new")}
              className="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium shadow-lg transform hover:scale-105 transition-all flex items-center space-x-2"
            >
              <svg
                className="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 4v16m8-8H4"
                />
              </svg>
              <span>Nouveau client</span>
            </button>
          </div>

          {/* Tableau des clients */}
          {clients.length === 0 ? (
            <div className="bg-white rounded-xl shadow-lg p-12 text-center">
              <svg
                className="mx-auto h-24 w-24 text-gray-400 mb-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                />
              </svg>
              <h3 className="text-xl font-semibold text-gray-700 mb-2">
                Aucun client pour le moment
              </h3>
              <p className="text-gray-500 mb-6">
                Commencez par créer votre premier client
              </p>
              <button
                onClick={() => navigate("/clients/new")}
                className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center space-x-2"
              >
                <svg
                  className="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 4v16m8-8H4"
                  />
                </svg>
                <span>Créer un client</span>
              </button>
            </div>
          ) : (
            <div className="bg-white rounded-xl shadow-lg overflow-hidden mb-12">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Client
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Contact
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Profession
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Situation
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Besoins
                      </th>
                      <th className="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {clients.map((client, index) => (
                      <tr
                        key={client.id}
                        className="hover:bg-gray-50 transition-colors"
                      >
                        {/* Client */}
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10">
                              <div className="h-10 w-10 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center text-white font-semibold">
                                {client.prenom?.charAt(0)}
                                {client.nom?.charAt(0)}
                              </div>
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-semibold text-gray-900">
                                {client.prenom} {client.nom.toUpperCase()}
                              </div>
                              <div className="text-xs text-gray-500">
                                ID: {client.id}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Contact */}
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">
                            {client.email && (
                              <div className="flex items-center space-x-1 mb-1">
                                <svg
                                  className="w-4 h-4 text-gray-400"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                  />
                                </svg>
                                <span className="text-xs truncate max-w-[150px]">
                                  {client.email}
                                </span>
                              </div>
                            )}
                            {client.telephone && (
                              <div className="flex items-center space-x-1">
                                <svg
                                  className="w-4 h-4 text-gray-400"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                  />
                                </svg>
                                <span className="text-xs">{client.telephone}</span>
                              </div>
                            )}
                            {!client.email && !client.telephone && (
                              <span className="text-xs text-gray-400">
                                Non renseigné
                              </span>
                            )}
                          </div>
                        </td>

                        {/* Profession */}
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900">
                            {client.profession || (
                              <span className="text-gray-400 text-xs">
                                Non renseignée
                              </span>
                            )}
                          </div>
                          {client.ville && (
                            <div className="text-xs text-gray-500 flex items-center mt-1">
                              <svg
                                className="w-3 h-3 mr-1 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                />
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                              </svg>
                              {client.ville}
                            </div>
                          )}
                        </td>

                        {/* Situation */}
                        <td className="px-6 py-4 whitespace-nowrap">
                          {client.situation_matrimoniale ? (
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                              {client.situation_matrimoniale}
                            </span>
                          ) : (
                            <span className="text-xs text-gray-400">
                              Non renseignée
                            </span>
                          )}
                        </td>

                        {/* Besoins */}
                        <td className="px-6 py-4">
                          {(() => {
                            // Nettoyer et décoder les besoins
                            let besoins: string[] = [];
                            if (client.besoins) {
                              if (typeof client.besoins === 'string') {
                                try {
                                  besoins = JSON.parse(client.besoins);
                                } catch {
                                  besoins = [client.besoins];
                                }
                              } else if (Array.isArray(client.besoins)) {
                                besoins = client.besoins;
                              }
                            }

                            // Capitaliser la première lettre
                            const formatBesoin = (b: string) => {
                              return b.charAt(0).toUpperCase() + b.slice(1).toLowerCase();
                            };

                            return besoins && besoins.length > 0 ? (
                              <div className="flex flex-wrap gap-1">
                                {besoins.slice(0, 2).map((besoin, idx) => (
                                  <span
                                    key={idx}
                                    className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700"
                                  >
                                    {formatBesoin(besoin)}
                                  </span>
                                ))}
                                {besoins.length > 2 && (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    +{besoins.length - 2}
                                  </span>
                                )}
                              </div>
                            ) : (
                              <span className="text-xs text-gray-400">
                                Aucun besoin
                              </span>
                            );
                          })()}
                        </td>

                        {/* Actions */}
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <div className="flex items-center justify-end space-x-2">
                            <button
                              onClick={() => navigate(`/clients/${client.id}`)}
                              className="text-indigo-600 hover:text-indigo-900 transition-colors p-1 hover:bg-indigo-50 rounded"
                              title="Voir la fiche"
                            >
                              <svg
                                className="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                              </svg>
                            </button>
                            <button
                              onClick={() =>
                                navigate(`/clients/${client.id}/edit`)
                              }
                              className="text-purple-600 hover:text-purple-900 transition-colors p-1 hover:bg-purple-50 rounded"
                              title="Éditer"
                            >
                              <svg
                                className="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                              </svg>
                            </button>
                            <button
                              onClick={() =>
                                handleDelete(client.id, client.nom, client.prenom)
                              }
                              className="text-red-600 hover:text-red-900 transition-colors p-1 hover:bg-red-50 rounded"
                              title="Supprimer"
                            >
                              <svg
                                className="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>

        {/* Dialogue de confirmation */}
        <ConfirmDialog
          isOpen={confirmDialog.isOpen}
          onClose={() => setConfirmDialog({ ...confirmDialog, isOpen: false })}
          onConfirm={confirmDialog.onConfirm}
          title={confirmDialog.title}
          message={confirmDialog.message}
          type="danger"
        />
      </div>
    </>
  );
};

export default HomePage;
