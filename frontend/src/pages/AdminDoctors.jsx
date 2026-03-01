import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const AdminDoctors = () => {
  const { user } = useAuth();
  const [doctors, setDoctors] = useState([]);
  const [specializations, setSpecializations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [specFilter, setSpecFilter] = useState('');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [doctorsRes, specsRes] = await Promise.all([
        api.getDoctors(),
        api.getSpecializations()
      ]);
      setDoctors(doctorsRes.data || []);
      setSpecializations(specsRes.data || []);
    } catch (err) {
      setError('Failed to load doctors');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleToggleStatus = async (doctorId) => {
    // Would need API endpoint
    alert('Doctor status toggled');
  };

  const filteredDoctors = doctors.filter(d => {
    const matchesSearch = d.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          d.license_number?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesSpec = !specFilter || d.specialization_id === parseInt(specFilter);
    return matchesSearch && matchesSpec;
  });

  if (loading) {
    return (
      <DashboardLayout title="Manage Doctors">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Manage Doctors">
      <div className="page-container">
        <div className="page-header">
          <h3>All Doctors</h3>
          <div className="header-actions">
            <div className="search-box">
              <input
                type="text"
                placeholder="Search doctors..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <select
              value={specFilter}
              onChange={(e) => setSpecFilter(e.target.value)}
            >
              <option value="">All Specializations</option>
              {specializations.map(spec => (
                <option key={spec.id} value={spec.id}>{spec.name}</option>
              ))}
            </select>
          </div>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredDoctors.length > 0 ? (
          <div className="doctors-grid">
            {filteredDoctors.map((doctor) => (
              <div key={doctor.id} className="doctor-card admin-card">
                <div className="doctor-header">
                  <div className="doctor-avatar-large">
                    {doctor.name?.charAt(0) || 'D'}
                  </div>
                  <div className="doctor-title">
                    <h4>Dr. {doctor.name}</h4>
                    <span className="specialization">
                      {doctor.specialization?.name || 'General Medicine'}
                    </span>
                  </div>
                </div>
                
                <div className="doctor-details">
                  <p><strong>License #:</strong> {doctor.license_number || 'N/A'}</p>
                  <p><strong>Qualification:</strong> {doctor.qualification || 'N/A'}</p>
                  <p><strong>Experience:</strong> {doctor.experience_years || 0} years</p>
                  <p><strong>Hospital:</strong> {doctor.hospital_clinic || 'N/A'}</p>
                  <p><strong>City:</strong> {doctor.city || 'N/A'}</p>
                  <p><strong>Fee:</strong> ৳{doctor.consultation_fee || 0}</p>
                  
                  {doctor.rating > 0 && (
                    <div className="rating">
                      <span className="stars">{'★'.repeat(Math.round(doctor.rating))}</span>
                      <span className="rating-value">{doctor.rating}</span>
                    </div>
                  )}
                </div>

                <div className="doctor-actions">
                  <button className="btn btn-sm btn-primary">View Profile</button>
                  <button 
                    className="btn btn-sm btn-secondary"
                    onClick={() => handleToggleStatus(doctor.id)}
                  >
                    Toggle Status
                  </button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="8.5" cy="7" r="4" />
            </svg>
            <h4>No Doctors Found</h4>
            <p>No doctors match your search criteria</p>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default AdminDoctors;
