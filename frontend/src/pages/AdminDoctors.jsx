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
  const [selectedDoctor, setSelectedDoctor] = useState(null);
  const [isEditMode, setIsEditMode] = useState(false);
  const [editFormData, setEditFormData] = useState({});
  const [editLoading, setEditLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalDoctors, setTotalDoctors] = useState(0);
  const perPage = 10;

  useEffect(() => {
    fetchData();
  }, [currentPage]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [doctorsRes, specsRes] = await Promise.all([
        api.getDoctors({ page: currentPage, per_page: perPage }),
        api.getSpecializations()
      ]);
      const doctorsData = doctorsRes.doctors || doctorsRes.data || [];
      const pagination = doctorsRes.pagination || { total: doctorsData.length, last_page: 1 };
      setDoctors(doctorsData);
      setTotalDoctors(pagination.total || doctorsData.length);
      setTotalPages(pagination.last_page || 1);
      setSpecializations(specsRes.specializations || specsRes.data || []);
    } catch (err) {
      setError('Failed to load doctors');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const filteredDoctors = doctors.filter(d => {
    const matchesSearch = d.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          d.license_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          d.specialization?.name?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesSpec = !specFilter || d.specialization?.id === parseInt(specFilter);
    return matchesSearch && matchesSpec;
  });

  const getStatusBadge = (doctor) => {
    if (doctor.is_available && doctor.is_verified) {
      return <span className="status-badge active">Active</span>;
    } else if (doctor.is_verified) {
      return <span className="status-badge pending">Verified</span>;
    }
    return <span className="status-badge inactive">Inactive</span>;
  };

  const handleViewProfile = (doctor) => {
    setSelectedDoctor(doctor);
    setIsEditMode(false);
  };

  const handleEditClick = (doctor) => {
    setSelectedDoctor(doctor);
    setEditFormData({
      name: doctor.name || '',
      qualification: doctor.qualification || '',
      experience_years: doctor.experience_years || 0,
      bio: doctor.bio || '',
      consultation_fee: doctor.consultation_fee || 0,
      hospital_clinic: doctor.hospital_clinic || '',
      address: doctor.address || '',
      city: doctor.city || '',
      languages: doctor.languages || ['English', 'Bengali'],
      available_days: doctor.available_days || [],
      start_time: doctor.working_hours?.start || '09:00',
      end_time: doctor.working_hours?.end || '17:00',
      slot_duration: doctor.slot_duration || 30,
      is_available: doctor.is_available ?? true,
      is_verified: doctor.is_verified ?? true,
    });
    setIsEditMode(true);
  };

  const handleEditFormChange = (e) => {
    const { name, value, type, checked } = e.target;
    setEditFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleDayToggle = (day) => {
    setEditFormData(prev => {
      const days = prev.available_days || [];
      if (days.includes(day)) {
        return { ...prev, available_days: days.filter(d => d !== day) };
      } else {
        return { ...prev, available_days: [...days, day] };
      }
    });
  };

  const handleSaveEdit = async () => {
    setEditLoading(true);
    try {
      const response = await api.updateDoctor(selectedDoctor.id, editFormData);
      if (response.success) {
        alert('Doctor updated successfully!');
        setIsEditMode(false);
        setSelectedDoctor(null);
        fetchData();
      } else {
        alert(response.error || 'Failed to update doctor');
      }
    } catch (err) {
      alert(err.message || 'Failed to update doctor');
    } finally {
      setEditLoading(false);
    }
  };

  const closeModal = () => {
    setSelectedDoctor(null);
    setIsEditMode(false);
    setEditFormData({});
  };

  if (loading) {
    return (
      <DashboardLayout title="Manage Doctors">
        <div className="loading-container">
          <div className="loading-spinner"></div>
          <p>Loading doctors...</p>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Manage Doctors">
      <div className="page-container">
        <div className="page-header">
          <div className="header-left">
            <h3>All Doctors ({totalDoctors})</h3>
            <p className="header-subtitle">Manage and view all registered doctors</p>
          </div>
          <div className="header-actions">
            <div className="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8" />
                <path d="M21 21l-4.35-4.35" />
              </svg>
              <input
                type="text"
                placeholder="Search doctors, specializations..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <select
              value={specFilter}
              onChange={(e) => setSpecFilter(e.target.value)}
              className="filter-select"
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
              <div key={doctor.id} className="doctor-card premium-card">
                <div className="card-header">
                  <div className="doctor-avatar-large">
                    {doctor.name?.charAt(0) || 'D'}
                  </div>
                  <div className="doctor-title">
                    <h4>{doctor.name || 'Unknown Doctor'}</h4>
                    <span className="specialization">
                      {doctor.specialization?.name || 'General Medicine'}
                    </span>
                    {getStatusBadge(doctor)}
                  </div>
                </div>
                
                <div className="doctor-details">
                  <div className="detail-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                    </svg>
                    <div>
                      <span className="label">License</span>
                      <span className="value">{doctor.license_number || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div className="detail-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                    </svg>
                    <div>
                      <span className="label">Qualification</span>
                      <span className="value">{doctor.qualification || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div className="detail-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <div>
                      <span className="label">Experience</span>
                      <span className="value">{doctor.experience_years || 0} Years</span>
                    </div>
                  </div>
                  
                  <div className="detail-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                      <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                    <div>
                      <span className="label">Hospital</span>
                      <span className="value">{doctor.hospital_clinic || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div className="detail-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    <div>
                      <span className="label">City</span>
                      <span className="value">{doctor.city || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div className="detail-row fee">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <line x1="12" y1="1" x2="12" y2="23" />
                      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                    <div>
                      <span className="label">Consultation Fee</span>
                      <span className="value price">৳{doctor.consultation_fee || 0}</span>
                    </div>
                  </div>
                  
                  {doctor.rating > 0 && (
                    <div className="rating">
                      <div className="stars">
                        {[...Array(5)].map((_, i) => (
                          <span key={i} className={i < Math.round(doctor.rating) ? 'star filled' : 'star'}>★</span>
                        ))}
                      </div>
                      <span className="rating-value">{doctor.rating} ({doctor.total_reviews || 0} reviews)</span>
                    </div>
                  )}
                </div>

                <div className="doctor-actions">
                  <button 
                    className="btn btn-secondary"
                    onClick={() => handleEditClick(doctor)}
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Edit
                  </button>
                  <button 
                    className="btn btn-primary"
                    onClick={() => handleViewProfile(doctor)}
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    View Profile
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
              <line x1="20" y1="8" x2="20" y2="14" />
              <line x1="23" y1="11" x2="17" y2="11" />
            </svg>
            <h4>No Doctors Found</h4>
            <p>No doctors match your search criteria</p>
          </div>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="pagination">
            <button 
              className="page-btn"
              onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
              disabled={currentPage === 1}
            >
              ← Previous
            </button>
            <span className="page-info">
              Page {currentPage} of {totalPages}
            </span>
            <button 
              className="page-btn"
              onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
              disabled={currentPage === totalPages}
            >
              Next →
            </button>
          </div>
        )}
      </div>

      {/* Doctor Profile Modal */}
      {selectedDoctor && !isEditMode && (
        <div className="modal-overlay" onClick={closeModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={closeModal}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            
            <div className="modal-header">
              <div className="modal-avatar">
                {selectedDoctor.name?.charAt(0) || 'D'}
              </div>
              <h2>{selectedDoctor.name || 'Unknown Doctor'}</h2>
              <span className="modal-specialization">
                {selectedDoctor.specialization?.name || 'General Medicine'}
              </span>
              {getStatusBadge(selectedDoctor)}
            </div>

            <div className="modal-body">
              <div className="modal-section">
                <h3>Personal Information</h3>
                <div className="info-grid">
                  <div className="info-item">
                    <span className="info-label">License Number</span>
                    <span className="info-value">{selectedDoctor.license_number || 'N/A'}</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">Qualification</span>
                    <span className="info-value">{selectedDoctor.qualification || 'N/A'}</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">Experience</span>
                    <span className="info-value">{selectedDoctor.experience_years || 0} Years</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">Languages</span>
                    <span className="info-value">{(selectedDoctor.languages || ['English', 'Bengali']).join(', ')}</span>
                  </div>
                </div>
              </div>

              <div className="modal-section">
                <h3>Practice Information</h3>
                <div className="info-grid">
                  <div className="info-item full-width">
                    <span className="info-label">Hospital/Clinic</span>
                    <span className="info-value">{selectedDoctor.hospital_clinic || 'N/A'}</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">Address</span>
                    <span className="info-value">{selectedDoctor.address || 'N/A'}</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">City</span>
                    <span className="info-value">{selectedDoctor.city || 'N/A'}</span>
                  </div>
                </div>
              </div>

              <div className="modal-section">
                <h3>Consultation</h3>
                <div className="info-grid">
                  <div className="info-item">
                    <span className="info-label">Consultation Fee</span>
                    <span className="info-value price">৳{selectedDoctor.consultation_fee || 0}</span>
                  </div>
                  <div className="info-item">
                    <span className="info-label">Rating</span>
                    <span className="info-value">
                      {selectedDoctor.rating > 0 ? `★ ${selectedDoctor.rating}/5 (${selectedDoctor.total_reviews || 0} reviews)` : 'Not rated'}
                    </span>
                  </div>
                </div>
              </div>

              {selectedDoctor.bio && (
                <div className="modal-section">
                  <h3>About</h3>
                  <p className="bio-text">{selectedDoctor.bio}</p>
                </div>
              )}

              {selectedDoctor.available_days && selectedDoctor.available_days.length > 0 && (
                <div className="modal-section">
                  <h3>Available Days</h3>
                  <div className="days-grid">
                    {['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].map(day => (
                      <span 
                        key={day} 
                        className={`day-badge ${selectedDoctor.available_days.includes(day) ? 'available' : ''}`}
                      >
                        {day.charAt(0).toUpperCase() + day.slice(1, 3)}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Edit Doctor Modal */}
      {selectedDoctor && isEditMode && (
        <div className="modal-overlay" onClick={closeModal}>
          <div className="modal-content edit-modal" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={closeModal}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            
            <div className="modal-header">
              <h2>Edit Doctor</h2>
              <span className="modal-specialization">
                {selectedDoctor.specialization?.name || 'General Medicine'}
              </span>
            </div>

            <div className="modal-body">
              <div className="edit-form">
                <div className="form-section">
                  <h3>Personal Information</h3>
                  <div className="form-grid">
                    <div className="form-group">
                      <label>Name</label>
                      <input
                        type="text"
                        name="name"
                        value={editFormData.name || ''}
                        onChange={handleEditFormChange}
                        placeholder="Doctor Name"
                      />
                    </div>
                    <div className="form-group">
                      <label>Qualification</label>
                      <input
                        type="text"
                        name="qualification"
                        value={editFormData.qualification || ''}
                        onChange={handleEditFormChange}
                        placeholder="e.g., MBBS, FCPS"
                      />
                    </div>
                    <div className="form-group">
                      <label>Experience (Years)</label>
                      <input
                        type="number"
                        name="experience_years"
                        value={editFormData.experience_years || 0}
                        onChange={handleEditFormChange}
                        min="0"
                        max="50"
                      />
                    </div>
                    <div className="form-group">
                      <label>Languages (comma separated)</label>
                      <input
                        type="text"
                        name="languages"
                        value={(editFormData.languages || []).join(', ')}
                        onChange={(e) => setEditFormData(prev => ({ 
                          ...prev, 
                          languages: e.target.value.split(',').map(l => l.trim()).filter(l => l) 
                        }))}
                        placeholder="English, Bengali"
                      />
                    </div>
                  </div>
                </div>

                <div className="form-section">
                  <h3>Practice Information</h3>
                  <div className="form-grid">
                    <div className="form-group full-width">
                      <label>Hospital/Clinic</label>
                      <input
                        type="text"
                        name="hospital_clinic"
                        value={editFormData.hospital_clinic || ''}
                        onChange={handleEditFormChange}
                        placeholder="Hospital or Clinic Name"
                      />
                    </div>
                    <div className="form-group full-width">
                      <label>Address</label>
                      <input
                        type="text"
                        name="address"
                        value={editFormData.address || ''}
                        onChange={handleEditFormChange}
                        placeholder="Full Address"
                      />
                    </div>
                    <div className="form-group">
                      <label>City</label>
                      <input
                        type="text"
                        name="city"
                        value={editFormData.city || ''}
                        onChange={handleEditFormChange}
                        placeholder="City"
                      />
                    </div>
                    <div className="form-group">
                      <label>Consultation Fee (৳)</label>
                      <input
                        type="number"
                        name="consultation_fee"
                        value={editFormData.consultation_fee || 0}
                        onChange={handleEditFormChange}
                        min="0"
                      />
                    </div>
                  </div>
                </div>

                <div className="form-section">
                  <h3>Schedule</h3>
                  <div className="form-grid">
                    <div className="form-group">
                      <label>Start Time</label>
                      <input
                        type="time"
                        name="start_time"
                        value={editFormData.start_time || '09:00'}
                        onChange={handleEditFormChange}
                      />
                    </div>
                    <div className="form-group">
                      <label>End Time</label>
                      <input
                        type="time"
                        name="end_time"
                        value={editFormData.end_time || '17:00'}
                        onChange={handleEditFormChange}
                      />
                    </div>
                    <div className="form-group">
                      <label>Slot Duration (minutes)</label>
                      <select
                        name="slot_duration"
                        value={editFormData.slot_duration || 30}
                        onChange={handleEditFormChange}
                      >
                        <option value={15}>15 min</option>
                        <option value={30}>30 min</option>
                        <option value={45}>45 min</option>
                        <option value={60}>60 min</option>
                      </select>
                    </div>
                  </div>
                  <div className="form-group full-width">
                    <label>Available Days</label>
                    <div className="days-selector">
                      {['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].map(day => (
                        <button
                          key={day}
                          type="button"
                          className={`day-toggle ${(editFormData.available_days || []).includes(day) ? 'active' : ''}`}
                          onClick={() => handleDayToggle(day)}
                        >
                          {day.charAt(0).toUpperCase() + day.slice(1, 3)}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="form-section">
                  <h3>Bio</h3>
                  <div className="form-group full-width">
                    <textarea
                      name="bio"
                      value={editFormData.bio || ''}
                      onChange={handleEditFormChange}
                      placeholder="Brief description about the doctor..."
                      rows={4}
                    />
                  </div>
                </div>

                <div className="form-section">
                  <h3>Status</h3>
                  <div className="checkbox-group">
                    <label className="checkbox-label">
                      <input
                        type="checkbox"
                        name="is_available"
                        checked={editFormData.is_available ?? true}
                        onChange={handleEditFormChange}
                      />
                      <span>Available for Booking</span>
                    </label>
                    <label className="checkbox-label">
                      <input
                        type="checkbox"
                        name="is_verified"
                        checked={editFormData.is_verified ?? true}
                        onChange={handleEditFormChange}
                      />
                      <span>Verified</span>
                    </label>
                  </div>
                </div>

                <div className="form-actions">
                  <button 
                    type="button" 
                    className="btn btn-secondary"
                    onClick={closeModal}
                  >
                    Cancel
                  </button>
                  <button 
                    type="button" 
                    className="btn btn-primary"
                    onClick={handleSaveEdit}
                    disabled={editLoading}
                  >
                    {editLoading ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
};

export default AdminDoctors;
