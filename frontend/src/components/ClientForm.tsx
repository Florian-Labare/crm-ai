import { useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../api/apiClient";

export default function ClientForm() {
  const [form, setForm] = useState({
    nom: "",
    prenom: "",
    profession: "",
  });
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await api.post("/clients", form);
    navigate("/clients");
  };

  return (
    <div className="bg-white p-6 shadow rounded-lg">
      <h2 className="text-2xl font-semibold text-gray-800 mb-6">Créer un nouveau client</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-gray-700 mb-1">Nom</label>
          <input
            placeholder="Nom"
            value={form.nom}
            onChange={(e) => setForm({ ...form, nom: e.target.value })}
            className="border border-gray-300 p-2 rounded w-full focus:ring focus:ring-indigo-200"
            required
          />
        </div>
        <div>
          <label className="block text-gray-700 mb-1">Prénom</label>
          <input
            placeholder="Prénom"
            value={form.prenom}
            onChange={(e) => setForm({ ...form, prenom: e.target.value })}
            className="border border-gray-300 p-2 rounded w-full focus:ring focus:ring-indigo-200"
          />
        </div>
        <div>
          <label className="block text-gray-700 mb-1">Profession</label>
          <input
            placeholder="Profession"
            value={form.profession}
            onChange={(e) => setForm({ ...form, profession: e.target.value })}
            className="border border-gray-300 p-2 rounded w-full focus:ring focus:ring-indigo-200"
          />
        </div>

        <button
          type="submit"
          className="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          Enregistrer
        </button>
      </form>
    </div>
  );
}
