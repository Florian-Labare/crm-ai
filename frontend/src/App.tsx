import { BrowserRouter as Router, Routes, Route, Navigate, useLocation } from "react-router-dom";
import { AuthProvider, useAuth } from "./contexts/AuthContext";
import HomePage from "./pages/HomePage";
import ClientDetailPage from "./pages/ClientDetailPage";
import ClientEditPage from "./pages/ClientEditPage";
import ClientForm from "./components/ClientForm";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";

function Navigation() {
  const location = useLocation();
  const { user, logout } = useAuth();
  const isHomePage = location.pathname === "/";
  const isAuthPage = ['/login', '/register'].includes(location.pathname);

  if (isHomePage || isAuthPage) return null;

  return (
    <header className="bg-white shadow-md sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        <a href="/" className="flex items-center space-x-2">
          <span className="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
            🎧 Whisper CRM
          </span>
        </a>
        <nav className="flex items-center space-x-6">
          <a href="/" className="text-gray-700 hover:text-indigo-600 font-medium transition-colors">
            Accueil
          </a>
          <a href="/clients/new" className="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md">
            + Nouveau client
          </a>
          {user && (
            <>
              <span className="text-gray-600">👤 {user.name}</span>
              <button onClick={logout} className="text-gray-700 hover:text-red-600 font-medium transition-colors">
                Déconnexion
              </button>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="flex justify-center items-center h-screen">Chargement...</div>;
  }

  return user ? <>{children}</> : <Navigate to="/login" />;
}

export default function App() {
  return (
    <Router>
      <AuthProvider>
        <div className="min-h-screen bg-gray-100 flex flex-col">
          <Navigation />
          <main className="flex-1">
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/" element={<ProtectedRoute><HomePage /></ProtectedRoute>} />
              <Route path="/clients/new" element={<ProtectedRoute><ClientForm /></ProtectedRoute>} />
              <Route path="/clients/:id" element={<ProtectedRoute><ClientDetailPage /></ProtectedRoute>} />
              <Route path="/clients/:id/edit" element={<ProtectedRoute><ClientEditPage /></ProtectedRoute>} />
              <Route path="*" element={<Navigate to="/" />} />
            </Routes>
          </main>
        </div>
      </AuthProvider>
    </Router>
  );
}