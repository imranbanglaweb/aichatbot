import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const DoctorPatients = () => {
  const { user } = useAuth();
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    fetchPatients();
  }, []);

  const fetchPatients = async () => {
    try {
      // Would need a dedicated endpoint for doctor patients
      // Using appointments for now
      const response = await api.getAppointments();
      const appointments = response.data || [];
      
      // Extract unique patients from appointments
      const uniquePatients = [];
      const seen = new Set();
      appointments.forEach(apt => {
        if (apt.patient && !seen.has(apt.patient.id)) {
          seen.add(apt.patient.id);
          uniquePatients.push({
            ...apt.patient,
            lastVisit: apt.appointment_date,
            totalVisits: appointments.filter(a => a.patient?.id === apt.patient.id).length
          });
        }
      });
      setPatients(uniquePatients);
    } catch (err) {
      setError('Failed to load patients');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const filteredPatients = patients.filter(patient => 
    patient.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    patient.email?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    patient.phone?.includes(searchTerm)
  );

  if (loading) {
    return (
      <DashboardLayout title="My Patients">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="My Patients">
      <div className="page-container">
        <div className="page-header">
          <h3>Patient List</h3>
          <div className="search-box">
            <input
              type="text"
              placeholder="Search patients..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredPatients.length > 0 ? (
          <div className="patients-grid">
            {filteredPatients.map((patient) => (
              <div key={patient.id} className="patient-card">
                <div className="patient-header">
                  <div className="patient-avatar-large">
                    {patient.name?.charAt(0) || 'P'}
                  </div>
                  <div className="patient-title">
                    <h4>{patient.name}</h4>
                    <span className="patient-id">ID: {patient.id}</span>
                  </div>
                </div>
                
                <div className="patient-details">
                  <p><strong>Email:</strong> {patient.email}</p>
                  <p><strong>Phone:</strong> {patient.phone || 'N/A'}</p>
                  <p><strong>Last Visit:</strong> {patient.lastVisit || 'N/A'}</p>
                  <p><strong>Total Visits:</strong> {patient.totalVisits || 0}</p>
                </div>

                <div className="patient-actions">
                  <button className="btn btn-sm btn-primary">View Details</button>
                  <button className="btn btn-sm btn-secondary">Medical History</button>
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
            <h4>No Patients Found</h4>
            <p>No patients match your search criteria</p>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default DoctorPatients;
