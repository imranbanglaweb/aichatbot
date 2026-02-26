import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const PatientDashboard = () => {
  const { user } = useAuth();
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.getPatientDashboard();
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
      <DashboardLayout title="Patient Dashboard">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  const stats = dashboardData?.stats || {};
  const upcomingAppointments = dashboardData?.upcoming_appointments || [];
  const recommendedDoctors = dashboardData?.recommended_doctors || [];

  return (
    <DashboardLayout title="Patient Dashboard">
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
            <h3>{stats.total_appointments || 0}</h3>
            <p>Total Appointments</p>
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
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{recommendedDoctors.length}</h3>
            <p>Recommended Doctors</p>
          </div>
        </div>
      </div>

      <div className="content-grid">
        <div className="content-card">
          <div className="card-header">
            <h3>Upcoming Appointments</h3>
            <a href="/patient/appointments" className="view-all">View All</a>
          </div>
          
          {upcomingAppointments.length > 0 ? (
            <div className="appointment-list">
              {upcomingAppointments.slice(0, 5).map((appointment) => (
                <div key={appointment.id} className="appointment-item">
                  <div className="appointment-avatar">
                    {appointment.doctor?.profile_image ? (
                      <img src={appointment.doctor.profile_image} alt={appointment.doctor.name} />
                    ) : (
                      appointment.doctor?.name?.charAt(0) || 'D'
                    )}
                  </div>
                  <div className="appointment-info">
                    <h4>Dr. {appointment.doctor?.name || 'Doctor'}</h4>
                    <p>{appointment.doctor?.specialization?.name || 'General Medicine'}</p>
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
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              <h4>No Upcoming Appointments</h4>
              <p>Book an appointment with a doctor</p>
            </div>
          )}
        </div>

        <div className="content-card">
          <div className="card-header">
            <h3>Quick Actions</h3>
          </div>
          
          <div className="quick-actions">
            <a href="/patient/doctors" className="quick-action-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <span>Find a Doctor</span>
            </a>
            
            <a href="/patient/appointments/book" className="quick-action-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
                <line x1="12" y1="14" x2="12" y2="18" />
                <line x1="10" y1="16" x2="14" y2="16" />
              </svg>
              <span>Book Appointment</span>
            </a>
            
            <a href="/patient/chat" className="quick-action-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
              </svg>
              <span>Chat with AI Assistant</span>
            </a>
            
            <a href="/patient/profile" className="quick-action-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              <span>My Profile</span>
            </a>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default PatientDashboard;
