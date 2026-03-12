import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Link } from 'react-router-dom';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const PatientAppointments = () => {
  const { user } = useAuth();
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState('upcoming');

  useEffect(() => {
    fetchAppointments();
  }, []);

  const fetchAppointments = async () => {
    try {
      const response = await api.getAppointments();
      // API returns {success: true, data: {appointments: [...], pagination: {...}}} now
      setAppointments(response.data?.appointments || []);
    } catch (err) {
      setError('Failed to load appointments');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = async (appointmentNumber) => {
    if (!window.confirm('Are you sure you want to cancel this appointment?')) return;
    
    try {
      await api.cancelAppointment(appointmentNumber);
      fetchAppointments(); // Refresh the list
    } catch (err) {
      alert(err.message || 'Failed to cancel appointment');
    }
  };

  const filteredAppointments = appointments.filter(apt => {
    if (activeTab === 'upcoming') return apt.status === 'scheduled' || apt.status === 'confirmed';
    if (activeTab === 'completed') return apt.status === 'completed';
    if (activeTab === 'cancelled') return apt.status === 'cancelled';
    return true;
  });

  if (loading) {
    return (
      <DashboardLayout title="My Appointments">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="My Appointments">
      <div className="page-container">
        <div className="tabs">
          <button 
            className={`tab ${activeTab === 'upcoming' ? 'active' : ''}`}
            onClick={() => setActiveTab('upcoming')}
          >
            Upcoming
          </button>
          <button 
            className={`tab ${activeTab === 'completed' ? 'active' : ''}`}
            onClick={() => setActiveTab('completed')}
          >
            Completed
          </button>
          <button 
            className={`tab ${activeTab === 'cancelled' ? 'active' : ''}`}
            onClick={() => setActiveTab('cancelled')}
          >
            Cancelled
          </button>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredAppointments.length > 0 ? (
          <div className="appointments-list">
            {filteredAppointments.map((appointment) => (
              <div key={appointment.id} className="appointment-card">
                <div className="appointment-header">
                  <div className="appointment-date">
                    <span className="date">{appointment.appointment_date}</span>
                    <span className="time">{appointment.appointment_time}</span>
                  </div>
                  <span className={`status status-${appointment.status}`}>
                    {appointment.status || 'scheduled'}
                  </span>
                </div>
                
                <div className="appointment-body">
                  <div className="doctor-info">
                    <div className="doctor-avatar">
                      {appointment.doctor?.name?.charAt(0) || 'D'}
                    </div>
                    <div className="doctor-details">
                      <h4>Dr. {appointment.doctor?.name || 'Doctor'}</h4>
                      <p>{appointment.doctor?.specialization?.name || 'General Medicine'}</p>
                    </div>
                  </div>
                  
                  <div className="appointment-details">
                    <p><strong>Appointment #:</strong> {appointment.appointment_number}</p>
                    <p><strong>Hospital:</strong> {appointment.doctor?.hospital_clinic || 'N/A'}</p>
                    <p><strong>Consultation Fee:</strong> ৳{appointment.doctor?.consultation_fee || 0}</p>
                  </div>
                </div>

                {appointment.status === 'scheduled' && (
                  <div className="appointment-actions">
                    <button 
                      className="btn btn-danger"
                      onClick={() => handleCancel(appointment.appointment_number)}
                    >
                      Cancel Appointment
                    </button>
                  </div>
                )}
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
            <h4>No {activeTab} Appointments</h4>
            <p>You don't have any {activeTab} appointments</p>
            {activeTab === 'upcoming' && (
              <Link to="/patient/doctors" className="btn btn-primary">
                Book an Appointment
              </Link>
            )}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default PatientAppointments;
