import { BrowserRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider, useAuth } from "./contexts/AuthContext";
import { PageProvider } from "./contexts/PageContext";
import { VuexyNavigation } from "./components/VuexyNavigation";
import { VuexySidebar } from "./components/VuexySidebar";
import HomePage from "./pages/HomePage";
import ClientsPage from "./pages/ClientsPage";
import ClientDetailPage from "./pages/ClientDetailPage";
import ClientEditPage from "./pages/ClientEditPage";
import ClientForm from "./components/ClientForm";
import DerFormPage from "./pages/DerFormPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import { RiskQuestionnaire } from "./pages/RiskQuestionnaire";
import ImportPage from "./pages/ImportPage";
import ComplianceDashboard from "./pages/ComplianceDashboard";
import AuthCallbackPage from "./pages/AuthCallbackPage";
import InvitationAcceptPage from "./pages/InvitationAcceptPage";
import CabinetSettingsPage from "./pages/CabinetSettingsPage";
import SuperAdminPage from "./pages/SuperAdminPage";
import OnboardingPage from "./pages/OnboardingPage";
import ProfilePage from "./pages/ProfilePage";
import ProductionPage from "./pages/ProductionPage";

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, loading, hasTeam } = useAuth();

  if (loading) {
    return <div className="flex justify-center items-center h-screen">Chargement...</div>;
  }

  if (!user) return <Navigate to="/login" />;
  if (!hasTeam) return <Navigate to="/onboarding" />;

  return <>{children}</>;
}

function AdminRoute({ children }: { children: React.ReactNode }) {
  const { user, loading, hasTeam, isAdmin } = useAuth();

  if (loading) {
    return <div className="flex justify-center items-center h-screen">Chargement...</div>;
  }

  if (!user) return <Navigate to="/login" />;
  if (!hasTeam) return <Navigate to="/onboarding" />;
  if (!isAdmin) return <Navigate to="/" />;

  return <>{children}</>;
}

function AppLayout() {
  const { user, hasTeam } = useAuth();
  const showShell = !!user && hasTeam;

  return (
    <div className="min-h-screen bg-[#F8F8F8] flex">
      {showShell && <VuexySidebar />}
      <div className="flex-1 flex flex-col min-w-0 overflow-x-hidden">
        {showShell && <VuexyNavigation />}
        <main className="flex-1 min-w-0">
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/auth/callback" element={<AuthCallbackPage />} />
            <Route path="/onboarding" element={<OnboardingPage />} />
            <Route path="/invitations/:token/accept" element={<InvitationAcceptPage />} />
            <Route path="/" element={<ProtectedRoute><HomePage /></ProtectedRoute>} />
            <Route path="/der/new" element={<ProtectedRoute><DerFormPage /></ProtectedRoute>} />
            <Route path="/clients" element={<ProtectedRoute><ClientsPage /></ProtectedRoute>} />
            <Route path="/clients/new" element={<ProtectedRoute><ClientForm /></ProtectedRoute>} />
            <Route path="/clients/:id" element={<ProtectedRoute><ClientDetailPage /></ProtectedRoute>} />
            <Route path="/clients/:id/edit" element={<ProtectedRoute><ClientEditPage /></ProtectedRoute>} />
            <Route path="/clients/:clientId/questionnaire-risque" element={<ProtectedRoute><RiskQuestionnaire /></ProtectedRoute>} />
            <Route path="/import" element={<ProtectedRoute><ImportPage /></ProtectedRoute>} />
            <Route path="/compliance-dashboard" element={<ProtectedRoute><ComplianceDashboard /></ProtectedRoute>} />
            <Route path="/settings/cabinet" element={<ProtectedRoute><CabinetSettingsPage /></ProtectedRoute>} />
            <Route path="/profile" element={<ProtectedRoute><ProfilePage /></ProtectedRoute>} />
            <Route path="/production" element={<AdminRoute><ProductionPage /></AdminRoute>} />
            <Route path="/admin" element={<ProtectedRoute><SuperAdminPage /></ProtectedRoute>} />
            <Route path="*" element={<Navigate to="/" />} />
          </Routes>
        </main>
      </div>
    </div>
  );
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Router>
        <AuthProvider>
          <PageProvider>
            <AppLayout />
          </PageProvider>
        </AuthProvider>
      </Router>
    </QueryClientProvider>
  );
}
