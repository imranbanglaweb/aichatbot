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

  const filteredDoctors = doctors.filter(doctor => {
    const matchesSearch = doctor.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          doctor.specialization?.name?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesSpecialization = !selectedSpecialization || 
      doctor.specialization_id === parseInt(selectedSpecialization);
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
    } catch (err) {
      alert(err.message || 'Failed to book appointment');
    } finally {
      setBookingLoading(false);
    }
  };

  if (loading) {
    return (
      <DashboardLayout title="Find Doctors">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Find Doctors">
      <div className="page-container">
        <div className="search-filters">
          <div className="search-box">
            <input
              type="text"
              placeholder="Search by doctor name or specialization..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          
          <select
            value={selectedSpecialization}
            onChange={(e) => setSelectedSpecialization(e.target.value)}
          >
            <option value="">All Specializations</option>
            {specializations.map(spec => (
              <option key={spec.id} value={spec.id}>{spec.name}</option>
            ))}
          </select>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredDoctors.length > 0 ? (
          <div className="doctors-grid">
            {filteredDoctors.map((doctor) => (
              <div key={doctor.id} className="doctor-card">
                <div className="doctor-header">
                  <div className="doctor-avatar-large">
                    {doctor.name?.charAt(0) || 'D'}
                  </div>
                  <div className="doctor-title">
                    <h3>Dr. {doctor.name}</h3>
                    <span className="specialization">
                      {doctor.specialization?.name || 'General Medicine'}
                    </span>
                  </div>
                </div>
                
                <div className="doctor-info">
                  <p><strong>Qualification:</strong> {doctor.qualification || 'N/A'}</p>
                  <p><strong>Experience:</strong> {doctor.experience_years || 0} years</p>
                  <p><strong>Hospital:</strong> {doctor.hospital_clinic || 'N/A'}</p>
                  <p><strong>City:</strong> {doctor.city || 'N/A'}</p>
                  <p><strong>Consultation Fee:</strong> ৳{doctor.consultation_fee || 0}</p>
                  
                  {doctor.rating > 0 && (
                    <div className="rating">
                      <span className="stars">{'★'.repeat(Math.round(doctor.rating))}</span>
                      <span className="rating-value">{doctor.rating}</span>
                      <span className="reviews">({doctor.total_reviews} reviews)</span>
                    </div>
                  )}
                </div>

                {doctor.bio && (
                  <p className="doctor-bio">{doctor.bio}</p>
                )}

                <button 
                  className="btn btn-primary"
                  onClick={() => setSelectedDoctor(doctor)}
                >
                  Book Appointment
                </button>
              </div>
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <h4>No Doctors Found</h4>
            <p>Try adjusting your search or filter criteria</p>
          </div>
        )}

        {/* Booking Modal */}
        {selectedDoctor && (
          <div className="modal-overlay" onClick={() => setSelectedDoctor(null)}>
            <div className="modal" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h3>Book Appointment</h3>
                <button className="close-btn" onClick={() => setSelectedDoctor(null)}>×</button>
              </div>
              
              <div className="modal-body">
                <div className="selected-doctor">
                  <h4>Dr. {selectedDoctor.name}</h4>
                  <p>{selectedDoctor.specialization?.name}</p>
                  <p>Fee: ৳{selectedDoctor.consultation_fee}</p>
                </div>
                
                <form onSubmit={(e) => {
                  e.preventDefault();
                  const formData = new FormData(e.target);
                  handleBookAppointment(
                    selectedDoctor.id,
                    formData.get('date'),
                    formData.get('time')
                  );
                }}>
                  <div className="form-group">
                    <label>Select Date</label>
                    <input type="date" name="date" required min={new Date().toISOString().split('T')[0]} />
                  </div>
                  
                  <div className="form-group">
                    <label>Select Time</label>
                    <select name="time" required>
                      <option value="">Choose time</option>
                      <option value="09:00">09:00 AM</option>
                      <option value="10:00">10:00 AM</option>
                      <option value="11:00">11:00 AM</option>
                      <option value="12:00">12:00 PM</option>
                      <option value="14:00">02:00 PM</option>
                      <option value="15:00">03:00 PM</option>
                      <option value="16:00">04:00 PM</option>
                      <option value="17:00">05:00 PM</option>
                    </select>
                  </div>
                  
                  <button type="submit" className="btn btn-primary" disabled={bookingLoading}>
                    {bookingLoading ? 'Booking...' : 'Confirm Booking'}
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default PatientDoctors;
