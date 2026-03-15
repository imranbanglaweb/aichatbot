import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { 
  faCalendarCheck, 
  faCalendarXmark, 
  faCalendarDay,
  faUserDoctor,
  faHospital,
  faMoneyBillWave,
  faHashtag,
  faClock,
  faStethoscope,
  faXmarkCircle,
  faChevronRight,
  faCalendarAlt,
  faCheckCircle,
  faTimesCircle
} from '@fortawesome/free-solid-svg-icons';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';
import './PatientAppointments.css';

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
      fetchAppointments();
    } catch (err) {
      alert(err.message || 'Failed to cancel appointment');
    }
  };

  const filteredAppointments = appointments.filter(apt => {
    if (activeTab === 'all') return true;
    if (activeTab === 'upcoming') return apt.status === 'pending' || apt.status === 'scheduled' || apt.status === 'confirmed';
    if (activeTab === 'completed') return apt.status === 'completed';
    if (activeTab === 'cancelled') return apt.status === 'cancelled';
    return true;
  });

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending':
      case 'scheduled':
      case 'confirmed':
        return <FontAwesomeIcon icon={faCalendarCheck} />;
      case 'completed':
        return <FontAwesomeIcon icon={faCheckCircle} />;
      case 'cancelled':
        return <FontAwesomeIcon icon={faTimesCircle} />;
      default:
        return <FontAwesomeIcon icon={faCalendarAlt} />;
    }
  };

  const getDoctorInitials = (name) => {
    if (!name) return 'D';
    const parts = name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.charAt(0).toUpperCase();
  };

  if (loading) {
    return (
      <DashboardLayout title="My Appointments">
        <div className="premium-loading">
          <div className="loading-spinner"></div>
          <p>Loading your appointments...</p>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="My Appointments">
      <div className="appointments-page">
        {/* Premium Tab Navigation */}
        <div className="premium-tabs">
          <button 
            className={`premium-tab ${activeTab === 'all' ? 'active' : ''}`}
            onClick={() => setActiveTab('all')}
          >
            <FontAwesomeIcon icon={faCalendarAlt} />
            <span>All</span>
            <div className="tab-indicator"></div>
          </button>
          <button 
            className={`premium-tab ${activeTab === 'upcoming' ? 'active' : ''}`}
            onClick={() => setActiveTab('upcoming')}
          >
            <FontAwesomeIcon icon={faCalendarCheck} />
            <span>Upcoming</span>
            <div className="tab-indicator"></div>
          </button>
          <button 
            className={`premium-tab ${activeTab === 'completed' ? 'active' : ''}`}
            onClick={() => setActiveTab('completed')}
          >
            <FontAwesomeIcon icon={faCalendarDay} />
            <span>Completed</span>
            <div className="tab-indicator"></div>
          </button>
          <button 
            className={`premium-tab ${activeTab === 'cancelled' ? 'active' : ''}`}
            onClick={() => setActiveTab('cancelled')}
          >
            <FontAwesomeIcon icon={faCalendarXmark} />
            <span>Cancelled</span>
            <div className="tab-indicator"></div>
          </button>
        </div>

        {error && (
          <div className="premium-error">
            <FontAwesomeIcon icon={faTimesCircle} />
            <span>{error}</span>
          </div>
        )}

        {filteredAppointments.length > 0 ? (
          <div className="appointments-grid">
            {filteredAppointments.map((appointment) => (
              <div key={appointment.id} className="premium-appointment-card">
                {/* Card Header with Gradient */}
                <div className="card-header-gradient">
                  <div className="appointment-date-badge">
                    <FontAwesomeIcon icon={faCalendarAlt} className="date-icon" />
                    <div className="date-info">
                      <span className="date">{appointment.date || appointment.appointment_date}</span>
                      <span className="time">
                        <FontAwesomeIcon icon={faClock} />
                        {appointment.time || appointment.appointment_time}
                      </span>
                    </div>
                  </div>
                  <div className={`status-badge-premium status-${appointment.status}`}>
                    {getStatusIcon(appointment.status)}
                    <span>{appointment.status === 'pending' ? 'Pending' : (appointment.status || 'scheduled')}</span>
                  </div>
                </div>

                {/* Card Body */}
                <div className="card-body">
                  {/* Doctor Info Section */}
                  <div className="doctor-profile-section">
                    <div className="doctor-avatar-premium">
                      {appointment.doctor_image ? (
                        <img src={appointment.doctor_image} alt="Doctor" />
                      ) : (
                        <span>{getDoctorInitials(appointment.doctor_name)}</span>
                      )}
                      <div className="avatar-ring"></div>
                    </div>
                    <div className="doctor-info-premium">
                      <h3>
                        <FontAwesomeIcon icon={faUserDoctor} />
                        {appointment.doctor_name || 'Doctor'}
                      </h3>
                      <p className="specialization">
                        <FontAwesomeIcon icon={faStethoscope} />
                        {appointment.specialization || 'General Medicine'}
                      </p>
                    </div>
                  </div>

                  {/* Divider */}
                  <div className="info-divider"></div>

                  {/* Appointment Details */}
                  <div className="appointment-details-grid">
                    <div className="detail-item">
                      <FontAwesomeIcon icon={faHashtag} className="detail-icon" />
                      <div className="detail-content">
                        <span className="detail-label">Appointment #</span>
                        <span className="detail-value">{appointment.appointment_number}</span>
                      </div>
                    </div>
                    <div className="detail-item">
                      <FontAwesomeIcon icon={faHospital} className="detail-icon" />
                      <div className="detail-content">
                        <span className="detail-label">Hospital</span>
                        <span className="detail-value">{appointment.hospital || 'N/A'}</span>
                      </div>
                    </div>
                    <div className="detail-item">
                      <FontAwesomeIcon icon={faMoneyBillWave} className="detail-icon" />
                      <div className="detail-content">
                        <span className="detail-label">Consultation Fee</span>
                        <span className="detail-value fee">৳{appointment.fee || 0}</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Card Actions */}
                {(appointment.status === 'pending' || appointment.status === 'scheduled' || appointment.status === 'confirmed') && (
                  <div className="card-actions">
                    <button 
                      className="btn-cancel-premium"
                      onClick={() => handleCancel(appointment.appointment_number)}
                    >
                      <FontAwesomeIcon icon={faXmarkCircle} />
                      Cancel Appointment
                    </button>
                  </div>
                )}

                {/* Card Glow Effect */}
                <div className="card-glow"></div>
              </div>
            ))}
          </div>
        ) : (
          /* Premium Empty State */
          <div className="premium-empty-state">
            <div className="empty-icon-wrapper">
              <FontAwesomeIcon icon={faCalendarAlt} />
            </div>
            <h3>No {activeTab === 'all' ? '' : activeTab} Appointments</h3>
            <p>You don't have any {activeTab} appointments at the moment</p>
            {activeTab === 'upcoming' && (
              <Link to="/patient/doctors" className="btn-book-premium">
                <FontAwesomeIcon icon={faCalendarCheck} />
                Book an Appointment
                <FontAwesomeIcon icon={faChevronRight} className="btn-arrow" />
              </Link>
            )}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default PatientAppointments;
