import { BrowserRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import ClientsPage from "./pages/ClientsPage";
import ClientDetailPage from "./pages/ClientDetailPage";
import ClientForm from "./components/ClientForm";

export default function App() {
  return (
    <Router>
      <div className="min-h-screen bg-gray-100 flex flex-col">
        <header className="bg-white shadow">
          <div className="max-w-5xl mx-auto px-6 py-4 flex justify-between items-center">
            <h1 className="text-2xl font-semibold text-indigo-600">🎧 Whisper CRM</h1>
            <nav className="space-x-4">
              <a href="/clients" className="text-gray-700 hover:text-indigo-600 font-medium">
                Clients
              </a>
              <a href="/clients/new" className="text-gray-700 hover:text-indigo-600 font-medium">
                Nouveau client
              </a>
            </nav>
          </div>
        </header>

        <main className="flex-1 max-w-5xl mx-auto w-full p-6">
          <Routes>
            <Route path="/clients" element={<ClientsPage />} />
            <Route path="/clients/new" element={<ClientForm />} />
            <Route path="/clients/:id" element={<ClientDetailPage />} />
            <Route path="*" element={<Navigate to="/clients" />} />
          </Routes>
        </main>
      </div>
    </Router>
  );
}