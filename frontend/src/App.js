import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Login from './pages/Login';
import PatientDashboard from './pages/PatientDashboard';
import PatientAppointments from './pages/PatientAppointments';
import PatientDoctors from './pages/PatientDoctors';
import PatientProfile from './pages/PatientProfile';
import PatientChat from './pages/PatientChat';
import DoctorDashboard from './pages/DoctorDashboard';
import DoctorAppointments from './pages/DoctorAppointments';
import DoctorSchedule from './pages/DoctorSchedule';
import DoctorPatients from './pages/DoctorPatients';
import DoctorProfile from './pages/DoctorProfile';
import DoctorChat from './pages/DoctorChat';
import AdminDashboard from './pages/AdminDashboard';
import AdminUsers from './pages/AdminUsers';
import AdminDoctors from './pages/AdminDoctors';
import AdminAppointments from './pages/AdminAppointments';
import AdminReports from './pages/AdminReports';

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
            <PatientAppointments />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/doctors" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <PatientDoctors />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/chat" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <PatientChat />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/patient/profile" 
        element={
          <ProtectedRoute allowedRoles={['patient']}>
            <PatientProfile />
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
            <DoctorAppointments />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/schedule" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <DoctorSchedule />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/patients" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <DoctorPatients />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/chat" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <DoctorChat />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/doctor/profile" 
        element={
          <ProtectedRoute allowedRoles={['doctor']}>
            <DoctorProfile />
          </ProtectedRoute>
        } 
      />

      {/* Admin Routes */}
      <Route 
        path="/admin/dashboard" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminDashboard />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/users" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminUsers />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/doctors" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminDoctors />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/appointments" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminAppointments />
          </ProtectedRoute>
        } 
      />
      <Route 
        path="/admin/reports" 
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminReports />
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
    <Router>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </Router>
  );
}

export default App;
