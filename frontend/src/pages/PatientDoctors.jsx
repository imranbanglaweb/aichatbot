import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const PatientDoctors = () => {
  const { user } = useAuth();
  const [doctors, setDoctors] = useState([]);
  const [specializations, setSpecializations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSpecialization, setSelectedSpecialization] = useState('');
  const [selectedDoctor, setSelectedDoctor] = useState(null);
  const [bookingLoading, setBookingLoading] = useState(false);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [availableSlots, setAvailableSlots] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
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

  const filteredDoctors = doctors.filter(doctor => {
    const matchesSearch = doctor.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          doctor.specialization?.name?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesSpecialization = !selectedSpecialization || 
      doctor.specialization?.id === parseInt(selectedSpecialization);
    return matchesSearch && matchesSpecialization;
  });

  const handleBookAppointment = async (doctorId, date, time) => {
    setBookingLoading(true);
    try {
      await api.bookAppointment({
        doctor_id: doctorId,
        appointment_date: date,
        appointment_time: time,
      });
      alert('Appointment booked successfully!');
      setSelectedDoctor(null);
      setSelectedDate('');
      setSelectedTime('');
    } catch (err) {
      alert(err.message || 'Failed to book appointment');
    } finally {
      setBookingLoading(false);
    }
  };

  const handleViewProfile = async (doctor) => {
    setSelectedDoctor({ ...doctor, isViewMode: true });
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const defaultDate = tomorrow.toISOString().split('T')[0];
    setSelectedDate(defaultDate);
    setSelectedTime('');
    setAvailableSlots([]);
    // Fetch slots for default date
    fetchDoctorSlots(doctor.id, defaultDate);
  };

  const fetchDoctorSlots = async (doctorId, date) => {
    if (!date) return;
    setSlotsLoading(true);
    try {
      const response = await api.getDoctorSlots(doctorId, date);
      if (response.success && response.slots) {
        setAvailableSlots(response.slots);
      } else {
        setAvailableSlots([]);
      }
    } catch (err) {
      console.error('Error fetching slots:', err);
      setAvailableSlots([]);
    } finally {
      setSlotsLoading(false);
    }
  };

  const handleDateChange = (date) => {
    setSelectedDate(date);
    setSelectedTime('');
    if (selectedDoctor && date) {
      fetchDoctorSlots(selectedDoctor.id, date);
    }
  };

  const handleBookClick = (doctor) => {
    setSelectedDoctor({ ...doctor, isViewMode: false });
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const defaultDate = tomorrow.toISOString().split('T')[0];
    setSelectedDate(defaultDate);
    setSelectedTime('');
    setAvailableSlots([]);
    // Fetch slots for default date
    fetchDoctorSlots(doctor.id, defaultDate);
  };

  const closeModal = () => {
    setSelectedDoctor(null);
    setSelectedDate('');
    setSelectedTime('');
    setAvailableSlots([]);
  };

  if (loading) {
    return (
      <DashboardLayout title="Find Doctors">
        <div className="loading-container">
          <div className="loading-spinner"></div>
          <p>Loading doctors...</p>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Find Doctors">
      <div className="page-container">
        <div className="page-header">
          <div className="header-left">
            <h3>Find Your Doctor ({totalDoctors})</h3>
            <p className="header-subtitle">Browse and book appointments with our expert doctors</p>
          </div>
          <div className="header-actions">
            <div className="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8" />
                <path d="M21 21l-4.35-4.35" />
              </svg>
              <input
                type="text"
                placeholder="Search by name or specialization..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <select
              value={selectedSpecialization}
              onChange={(e) => setSelectedSpecialization(e.target.value)}
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
                    {doctor.rating > 0 && (
                      <div className="rating-inline">
                        <span className="stars">
                          {[...Array(5)].map((_, i) => (
                            <span key={i} className={i < Math.round(doctor.rating) ? 'star filled' : 'star'}>★</span>
                          ))}
                        </span>
                        <span className="rating-value">{doctor.rating}</span>
                      </div>
                    )}
                  </div>
                </div>
                
                <div className="doctor-details">
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
                  
                  {doctor.available_days && doctor.available_days.length > 0 && (
                    <div className="available-days">
                      <span className="label">Available</span>
                      <div className="days">
                        {doctor.available_days.slice(0, 5).map(day => (
                          <span key={day} className="day">{day.slice(0, 3)}</span>
                        ))}
                        {doctor.available_days.length > 5 && <span className="day more">+{doctor.available_days.length - 5}</span>}
                      </div>
                    </div>
                  )}
                </div>

                <div className="doctor-actions">
                  <button 
                    className="btn btn-secondary"
                    onClick={() => handleViewProfile(doctor)}
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    View
                  </button>
                  <button 
                    className="btn btn-primary"
                    onClick={() => handleBookClick(doctor)}
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Book Now
                  </button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="11" cy="11" r="8" />
              <path d="M21 21l-4.35-4.35" />
            </svg>
            <h4>No Doctors Found</h4>
            <p>Try adjusting your search or filter criteria</p>
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
      {selectedDoctor && (
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
              {selectedDoctor.rating > 0 && (
                <div className="rating">
                  <span className="stars">
                    {[...Array(5)].map((_, i) => (
                      <span key={i} className={i < Math.round(selectedDoctor.rating) ? 'star filled' : 'star'}>★</span>
                    ))}
                  </span>
                  <span className="rating-value">{selectedDoctor.rating}/5 ({selectedDoctor.total_reviews || 0} reviews)</span>
                </div>
              )}
            </div>

            <div className="modal-body">
              <div className="modal-section">
                <h3>Personal Information</h3>
                <div className="info-grid">
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
                <h3>Consultation Fee</h3>
                <div className="info-grid">
                  <div className="info-item">
                    <span className="info-label">Per Visit</span>
                    <span className="info-value price">৳{selectedDoctor.consultation_fee || 0}</span>
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

              {/* Booking Section */}
              <div className="modal-section booking-section">
                <h3>Book Appointment</h3>
                <div className="booking-form">
                  <div className="form-group">
                    <label>Select Date</label>
                    {/* Quick date selection for next 7 days */}
                    <div className="quick-dates">
                      {[...Array(7)].map((_, i) => {
                        const date = new Date();
                        date.setDate(date.getDate() + i + 1);
                        const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase();
                        const dateStr = date.toISOString().split('T')[0];
                        const isAvailable = selectedDoctor.available_days?.includes(dayOfWeek);
                        return (
                          <button
                            key={i}
                            type="button"
                            className={`quick-date-btn ${selectedDate === dateStr ? 'selected' : ''} ${!isAvailable ? 'unavailable' : ''}`}
                            onClick={() => isAvailable && handleDateChange(dateStr)}
                            disabled={!isAvailable}
                          >
                            <span className="day-name">{date.toLocaleDateString('en-US', { weekday: 'short' })}</span>
                            <span className="day-date">{date.getDate()}</span>
                          </button>
                        );
                      })}
                    </div>
                    <input 
                      type="date" 
                      value={selectedDate}
                      onChange={(e) => handleDateChange(e.target.value)}
                      min={new Date(Date.now() + 86400000).toISOString().split('T')[0]}
                    />
                  </div>
                  <div className="form-group">
                    <label>Select Time</label>
                    {slotsLoading ? (
                      <div className="slots-loading">Loading available slots...</div>
                    ) : availableSlots.length > 0 ? (
                      <div className="slots-grid">
                        {availableSlots.map((slot, index) => (
                          <button
                            key={index}
                            type="button"
                            className={`slot-btn ${selectedTime === slot.time ? 'selected' : ''} ${!slot.available ? 'unavailable' : ''}`}
                            onClick={() => slot.available && setSelectedTime(slot.time)}
                            disabled={!slot.available}
                          >
                            {slot.formatted}
                          </button>
                        ))}
                      </div>
                    ) : selectedDate ? (
                      <div className="no-slots">No available slots for this date</div>
                    ) : (
                      <div className="no-slots">Please select a date to see available time slots</div>
                    )}
                  </div>
                  <button 
                    className="btn btn-primary btn-full"
                    disabled={!selectedDate || !selectedTime || bookingLoading}
                    onClick={() => handleBookAppointment(selectedDoctor.id, selectedDate, selectedTime)}
                  >
                    {bookingLoading ? 'Booking...' : 'Confirm Booking - ৳' + (selectedDoctor.consultation_fee || 0)}
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

export default PatientDoctors;
