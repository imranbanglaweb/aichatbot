import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Link } from 'react-router-dom';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const AdminDashboard = () => {
  const { user } = useAuth();
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const response = await api.getAdminDashboard();
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
      <DashboardLayout title="Admin Dashboard">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  const stats = dashboardData?.stats || {};
  const todayAppointments = dashboardData?.today_appointments || [];
  const recentUsers = dashboardData?.recent_users || [];

  return (
    <DashboardLayout title="Admin Dashboard">
      <div className="welcome-section">
        <div className="welcome-info">
          <h2>Welcome back, Admin!</h2>
          <p>Here's an overview of your healthcare portal.</p>
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{stats.total_users || 0}</h3>
            <p>Total Patients</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="8.5" cy="7" r="4" />
              <line x1="20" y1="8" x2="20" y2="14" />
              <line x1="23" y1="11" x2="17" y2="11" />
            </svg>
          </div>
          <div className="stat-info">
            <h3>{stats.total_doctors || 0}</h3>
            <p>Total Doctors</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon purple">
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
          <div className="stat-icon orange">
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
      </div>

      <div className="content-grid">
        <div className="content-card">
          <div className="card-header">
            <h3>Today's Appointments</h3>
            <Link to="/admin/appointments" className="view-all">View All</Link>
          </div>
          
          {todayAppointments.length > 0 ? (
            <div className="appointment-list">
              {todayAppointments.slice(0, 5).map((appointment) => (
                <div key={appointment.id} className="appointment-item">
                  <div className="appointment-avatar">
                    {appointment.patient?.name?.charAt(0) || 'P'}
                  </div>
                  <div className="appointment-info">
                    <h4>{appointment.patient?.name || 'Patient'}</h4>
                    <p>Dr. {appointment.doctor?.name || 'Doctor'}</p>
                  </div>
                  <div className="appointment-time">
                    <div className="time">{appointment.start_time}</div>
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
              <p>All clear!</p>
            </div>
          )}
        </div>

        <div className="content-card">
          <div className="card-header">
            <h3>Recent Registrations</h3>
            <Link to="/admin/users" className="view-all">View All</Link>
          </div>
          
          {recentUsers.length > 0 ? (
            <div className="appointment-list">
              {recentUsers.slice(0, 5).map((u) => (
                <div key={u.id} className="appointment-item">
                  <div className="appointment-avatar">
                    {u.name?.charAt(0) || 'U'}
                  </div>
                  <div className="appointment-info">
                    <h4>{u.name || 'User'}</h4>
                    <p>{u.email}</p>
                  </div>
                  <div className="appointment-time">
                    <div className="date">{new Date(u.created_at).toLocaleDateString()}</div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
              </svg>
              <h4>No Recent Users</h4>
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
          <Link to="/admin/doctors" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="8.5" cy="7" r="4" />
              <line x1="20" y1="8" x2="20" y2="14" />
              <line x1="23" y1="11" x2="17" y2="11" />
            </svg>
            <span>Manage Doctors</span>
          </Link>
          
          <Link to="/admin/users" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
            </svg>
            <span>View Patients</span>
          </Link>
          
          <Link to="/admin/appointments" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <span>All Appointments</span>
          </Link>
          
          <Link to="/admin/reports" className="quick-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="20" x2="12" y2="10" />
              <line x1="18" y1="20" x2="18" y2="4" />
              <line x1="6" y1="20" x2="6" y2="16" />
            </svg>
            <span>Reports</span>
          </Link>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default AdminDashboard;
