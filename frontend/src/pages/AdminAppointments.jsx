import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const AdminAppointments = () => {
  const { user } = useAuth();
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('');

  useEffect(() => {
    fetchAppointments();
  }, []);

  const fetchAppointments = async () => {
    try {
      const response = await api.getAppointments();
      // API returns {success: true, data: {appointments: [...], pagination: {...}}}
      setAppointments(response.data?.appointments || []);
    } catch (err) {
      setError('Failed to load appointments');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateStatus = async (appointmentId, status) => {
    // Would need API endpoint
    alert(`Appointment ${status}`);
  };

  const filteredAppointments = appointments.filter(apt => {
    const matchesStatus = !statusFilter || apt.status === statusFilter;
    const matchesDate = !dateFilter || apt.appointment_date === dateFilter;
    return matchesStatus && matchesDate;
  });

  if (loading) {
    return (
      <DashboardLayout title="Manage Appointments">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Manage Appointments">
      <div className="page-container">
        <div className="page-header">
          <h3>All Appointments</h3>
          <div className="header-actions">
            <input
              type="date"
              value={dateFilter}
              onChange={(e) => setDateFilter(e.target.value)}
              placeholder="Filter by date"
            />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="">All Status</option>
              <option value="scheduled">Scheduled</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredAppointments.length > 0 ? (
          <div className="appointments-table">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Date & Time</th>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredAppointments.map((apt) => (
                  <tr key={apt.id}>
                    <td>{apt.appointment_number}</td>
                    <td>
                      <div className="datetime-cell">
                        <span className="date">{apt.appointment_date}</span>
                        <span className="time">{apt.appointment_time}</span>
                      </div>
                    </td>
                    <td>
                      <div className="patient-cell">
                        <div className="patient-avatar">
                          {apt.patient?.name?.charAt(0) || 'P'}
                        </div>
                        <div className="patient-info">
                          <span className="name">{apt.patient?.name || 'Patient'}</span>
                          <span className="phone">{apt.patient?.phone || 'N/A'}</span>
                        </div>
                      </div>
                    </td>
                    <td>Dr. {apt.doctor?.name || 'Doctor'}</td>
                    <td>
                      <span className={`status-badge status-${apt.status}`}>
                        {apt.status || 'scheduled'}
                      </span>
                    </td>
                    <td>
                      <div className="action-buttons">
                        <button className="btn btn-sm btn-primary">View</button>
                        <select 
                          className="status-select"
                          value={apt.status}
                          onChange={(e) => handleUpdateStatus(apt.id, e.target.value)}
                        >
                          <option value="scheduled">Scheduled</option>
                          <option value="confirmed">Confirm</option>
                          <option value="completed">Complete</option>
                          <option value="cancelled">Cancel</option>
                        </select>
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
            <h4>No Appointments Found</h4>
            <p>No appointments match your filter criteria</p>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default AdminAppointments;
