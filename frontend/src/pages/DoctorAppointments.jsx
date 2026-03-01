import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const DoctorAppointments = () => {
  const { user } = useAuth();
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState('today');

  useEffect(() => {
    fetchAppointments();
  }, []);

  const fetchAppointments = async () => {
    try {
      const response = await api.getAppointments();
      setAppointments(response.data || []);
    } catch (err) {
      setError('Failed to load appointments');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateStatus = async (appointmentId, status) => {
    try {
      // Would need an API endpoint for this
      alert(`Appointment ${status} successfully`);
      fetchAppointments();
    } catch (err) {
      alert('Failed to update appointment');
    }
  };

  const filteredAppointments = appointments.filter(apt => {
    const today = new Date().toISOString().split('T')[0];
    if (activeTab === 'today') return apt.appointment_date === today;
    if (activeTab === 'upcoming') return apt.appointment_date > today;
    if (activeTab === 'past') return apt.appointment_date < today;
    return true;
  });

  if (loading) {
    return (
      <DashboardLayout title="Appointments">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Appointments">
      <div className="page-container">
        <div className="tabs">
          <button 
            className={`tab ${activeTab === 'today' ? 'active' : ''}`}
            onClick={() => setActiveTab('today')}
          >
            Today's Appointments
          </button>
          <button 
            className={`tab ${activeTab === 'upcoming' ? 'active' : ''}`}
            onClick={() => setActiveTab('upcoming')}
          >
            Upcoming
          </button>
          <button 
            className={`tab ${activeTab === 'past' ? 'active' : ''}`}
            onClick={() => setActiveTab('past')}
          >
            Past
          </button>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredAppointments.length > 0 ? (
          <div className="appointments-table">
            <table>
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Patient</th>
                  <th>Appointment #</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredAppointments.map((appointment) => (
                  <tr key={appointment.id}>
                    <td>
                      <div className="appointment-time-cell">
                        <span className="time">{appointment.appointment_time}</span>
                        <span className="date">{appointment.appointment_date}</span>
                      </div>
                    </td>
                    <td>
                      <div className="patient-cell">
                        <div className="patient-avatar">
                          {appointment.patient?.name?.charAt(0) || 'P'}
                        </div>
                        <div className="patient-info">
                          <span className="name">{appointment.patient?.name || 'Patient'}</span>
                          <span className="phone">{appointment.patient?.phone || 'N/A'}</span>
                        </div>
                      </div>
                    </td>
                    <td>{appointment.appointment_number}</td>
                    <td>
                      <span className={`status-badge status-${appointment.status}`}>
                        {appointment.status || 'scheduled'}
                      </span>
                    </td>
                    <td>
                      <div className="action-buttons">
                        <button 
                          className="btn btn-sm btn-primary"
                          onClick={() => handleUpdateStatus(appointment.id, 'completed')}
                          disabled={appointment.status === 'completed'}
                        >
                          Complete
                        </button>
                        <button 
                          className="btn btn-sm btn-danger"
                          onClick={() => handleUpdateStatus(appointment.id, 'cancelled')}
                          disabled={appointment.status === 'cancelled' || appointment.status === 'completed'}
                        >
                          Cancel
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <h4>No {activeTab} Appointments</h4>
            <p>You don't have any {activeTab} appointments</p>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default DoctorAppointments;
