import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Link } from 'react-router-dom';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const DoctorDashboard = () => {
  const { user } = useAuth();
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.getDoctorDashboard();
      setDashboardData(response.data);
    } catch (err) {
      setError('Failed to load dashboard data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <DashboardLayout title="Doctor Dashboard">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  const doctor = dashboardData?.doctor || {};
  const stats = dashboardData?.stats || {};
  const todayAppointments = dashboardData?.today_appointments || [];
  const upcomingAppointments = dashboardData?.upcoming_appointments || [];

  return (
    <DashboardLayout title="Doctor Dashboard">
      <div className="welcome-section">
        <div className="welcome-info">
          <h2>Welcome back, Dr. {user?.name || 'Doctor'}!</h2>
          <p>Here's what's happening with your practice today.</p>
        </div>
        {doctor.specialization && (
          <div className="specialization-badge">
            {doctor.specialization}
          </div>
        )}
      </div>

      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{stats.today_appointments || 0}</h3>
            <p>Today's Appointments</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{stats.upcoming_appointments || 0}</h3>
            <p>Upcoming</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{stats.completed_appointments || 0}</h3>
            <p>Completed</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="20" x2="12" y2="10" />
              <line x1="18" y1="20" x2="18" y2="4" />
              <line x1="6" y1="20" x2="6" y2="16" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{doctor.rating || 'N/A'}</h3>
            <p>Rating</p>
          </div>
        </div>
      </div>

      <div className="content-grid">
        <div className="content-card">
          <div className="card-header">
            <h3>Today's Schedule</h3>
            <Link to="/doctor/appointments" className="view-all">View All</Link>
          </div>
          
          {todayAppointments.length > 0 ? (
            <div className="appointment-list">
              {todayAppointments.map((appointment) => (
                <div key={appointment.id} className="appointment-item">
                  <div className="appointment-avatar">
                    {appointment.patient?.profile_image ? (
                      <img src={appointment.patient.profile_image} alt={appointment.patient.name} />
                    ) : (
                      appointment.patient?.name?.charAt(0) || 'P'
                    )}
                  </div>
                  <div className="appointment-info">
                    <h4>{appointment.patient?.name || 'Patient'}</h4>
                    <p>{appointment.appointment_number || 'General Visit'}</p>
                  </div>
                  <div className="appointment-time">
                    <div className="time">{appointment.appointment_time}</div>
                    <div className={`status status-${appointment.status}`}>
                      {appointment.status || 'scheduled'}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              <h4>No Appointments Today</h4>
              <p>Enjoy your free day!</p>
            </div>
          )}
        </div>

        <div className="content-card">
          <div className="card-header">
            <h3>Upcoming Appointments</h3>
          </div>
          
          {upcomingAppointments.length > 0 ? (
            <div className="appointment-list">
              {upcomingAppointments.slice(0, 5).map((appointment) => (
                <div key={appointment.id} className="appointment-item">
                  <div className="appointment-avatar">
                    {appointment.patient?.name?.charAt(0) || 'P'}
                  </div>
                  <div className="appointment-info">
                    <h4>{appointment.patient?.name || 'Patient'}</h4>
                    <p>{appointment.appointment_number || 'General Visit'}</p>
                  </div>
                  <div className="appointment-time">
                    <div className="time">{appointment.appointment_time}</div>
                    <div className="date">{appointment.appointment_date}</div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
              <h4>No Upcoming Appointments</h4>
              <p>Check back later</p>
            </div>
          )}
        </div>
      </div>

      <div className="content-card" style={{ marginTop: '24px' }}>
        <div className="card-header">
          <h3>Quick Actions</h3>
        </div>
        
        <div className="quick-actions">
          <Link to="/doctor/schedule" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <span>Manage Schedule</span>
          </Link>
          
          <Link to="/doctor/patients" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
            </svg>
            <span>View Patients</span>
          </Link>
          
          <Link to="/doctor/chat" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
            </svg>
            <span>AI Assistant</span>
          </Link>
          
          <Link to="/doctor/profile" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            <span>My Profile</span>
          </Link>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default DoctorDashboard;
