import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Login from './pages/Login';
import PatientDashboard from './pages/PatientDashboard';
import DoctorDashboard from './pages/DoctorDashboard';

// Protected Route Component
const ProtectedRoute = ({ children, allowedRoles }) => {
  const { isAuthenticated, user, loading } = useAuth();

  if (loading) {
    return <div>Loading...</div>;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user?.role)) {
    return <Navigate to={`/${user?.role}/dashboard`} replace />;
  }

  return children;
};

// Public Route Component (redirect if already logged in)
const PublicRoute = ({ children }) => {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <div>Loading...</div>;
  }

  if (isAuthenticated) {
    const role = localStorage.getItem('userRole') || 'patient';
    return <Navigate to={`/${role}/dashboard`} replace />;
  }

  return children;
};

// ChatBot Page (for AI assistant)
const ChatBotPage = () => {
  const { user } = useAuth();
  return (
    <div className="chat-page">
      <h1>AI Health Assistant</h1>
      <p>Welcome, {user?.name}!</p>
      {/* ChatBot component will be rendered here */}
      <div style={{ padding: '20px', textAlign: 'center', color: '#666' }}>
        Chat interface coming soon...
      </div>
    </div>
  );
};

function AppRoutes() {
  return (
    <Routes>
      <Route 
        path="/login" 
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        } 
      />
      
      {/* Patient Routes */}
      <Route 
        path="/patient/dashboard" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <PatientDashboard />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/appointments" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <div className="page">Appointments - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/doctors" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <div className="page">Find Doctors - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/chat" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <ChatBotPage />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/profile" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <div className="page">Profile - Coming Soon</div>
          </ProtectedRoute>
        } 
      />

      {/* Doctor Routes */}
      <Route 
        path="/doctor/dashboard" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <DoctorDashboard />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/appointments" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <div className="page">Appointments - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/schedule" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <div className="page">Schedule - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/patients" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <div className="page">Patients - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/chat" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <ChatBotPage />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/profile" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <div className="page">Profile - Coming Soon</div>
          </ProtectedRoute>
        } 
      />

      {/* Admin Routes */}
      <Route 
        path="/admin/dashboard" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <div className="page">Admin Dashboard - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/users" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <div className="page">Users - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/doctors" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <div className="page">Doctors - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/appointments" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <div className="page">Appointments - Coming Soon</div>
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/reports" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <div className="page">Reports - Coming Soon</div>
          </ProtectedRoute>
        } 
      />

      {/* Default redirect */}
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}

function App() {
  return (
    <AuthProvider>
      <Router>
        <AppRoutes />
      </Router>
    </AuthProvider>
  );
}

export default App;
