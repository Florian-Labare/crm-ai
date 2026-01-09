import { BrowserRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "./contexts/AuthContext";
import { VuexyNavigation } from "./components/VuexyNavigation";
import HomePage from "./pages/HomePage";
import ClientDetailPage from "./pages/ClientDetailPage";
import ClientEditPage from "./pages/ClientEditPage";
import ClientForm from "./components/ClientForm";
import DerFormPage from "./pages/DerFormPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import { RiskQuestionnaire } from "./pages/RiskQuestionnaire";
import ImportPage from "./pages/ImportPage";

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
        <div className="min-h-screen bg-[#F8F8F8] flex flex-col">
          <VuexyNavigation />
          <main className="flex-1">
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/" element={<ProtectedRoute><HomePage /></ProtectedRoute>} />
              <Route path="/der/new" element={<ProtectedRoute><DerFormPage /></ProtectedRoute>} />
              <Route path="/clients/new" element={<ProtectedRoute><ClientForm /></ProtectedRoute>} />
              <Route path="/clients/:id" element={<ProtectedRoute><ClientDetailPage /></ProtectedRoute>} />
              <Route path="/clients/:id/edit" element={<ProtectedRoute><ClientEditPage /></ProtectedRoute>} />
              <Route path="/clients/:clientId/questionnaire-risque" element={<ProtectedRoute><RiskQuestionnaire /></ProtectedRoute>} />
              <Route path="/import" element={<ProtectedRoute><ImportPage /></ProtectedRoute>} />
              <Route path="*" element={<Navigate to="/" />} />
            </Routes>
          </main>
        </div>
      </AuthProvider>
    </Router>
  );
}