import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const DoctorProfile = () => {
  const { user, updateUser } = useAuth();
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });
  const [formData, setFormData] = useState({
    name: user?.name || '',
    phone: user?.phone || '',
    qualification: user?.qualification || '',
    bio: user?.bio || '',
    hospital_clinic: user?.hospital_clinic || '',
    address: user?.address || '',
    city: user?.city || '',
    consultation_fee: user?.consultation_fee || '',
    languages: user?.languages || '',
  });

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      await api.updateProfile(formData);
      updateUser(formData);
      setMessage({ type: 'success', text: 'Profile updated successfully!' });
    } catch (err) {
      setMessage({ type: 'error', text: err.message || 'Failed to update profile' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <DashboardLayout title="My Profile">
      <div className="page-container">
        {message.text && (
          <div className={`message ${message.type}`}>
            {message.text}
          </div>
        )}

        <div className="profile-sections">
          {/* Professional Information */}
          <div className="profile-section">
            <h3>Professional Information</h3>
            <form onSubmit={handleSubmit} className="profile-form">
              <div className="form-row">
                <div className="form-group">
                  <label>Full Name</label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    required
                  />
                </div>
                
                <div className="form-group">
                  <label>Phone Number</label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                  />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>Qualification</label>
                  <input
                    type="text"
                    name="qualification"
                    value={formData.qualification}
                    onChange={handleChange}
                    placeholder="e.g., MBBS, FCPS"
                  />
                </div>
                
                <div className="form-group">
                  <label>Consultation Fee (৳)</label>
                  <input
                    type="number"
                    name="consultation_fee"
                    value={formData.consultation_fee}
                    onChange={handleChange}
                  />
                </div>
              </div>

              <div className="form-group">
                <label>Hospital/Clinic</label>
                <input
                  type="text"
                  name="hospital_clinic"
                  value={formData.hospital_clinic}
                  onChange={handleChange}
                />
              </div>

              <div className="form-group">
                <label>Address</label>
                <textarea
                  name="address"
                  value={formData.address}
                  onChange={handleChange}
                  rows="2"
                />
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>City</label>
                  <input
                    type="text"
                    name="city"
                    value={formData.city}
                    onChange={handleChange}
                  />
                </div>
                
                <div className="form-group">
                  <label>Languages</label>
                  <input
                    type="text"
                    name="languages"
                    value={formData.languages}
                    onChange={handleChange}
                    placeholder="e.g., English, Bengali, Hindi"
                  />
                </div>
              </div>

              <div className="form-group">
                <label>Bio</label>
                <textarea
                  name="bio"
                  value={formData.bio}
                  onChange={handleChange}
                  rows="4"
                  placeholder="Tell patients about yourself and your expertise..."
                />
              </div>

              <button type="submit" className="btn btn-primary" disabled={loading}>
                {loading ? 'Saving...' : 'Save Changes'}
              </button>
            </form>
          </div>

          {/* Account Information */}
          <div className="profile-section">
            <h3>Account Information</h3>
            <div className="account-info">
              <div className="info-item">
                <span className="label">Email:</span>
                <span className="value">{user?.email || 'N/A'}</span>
              </div>
              <div className="info-item">
                <span className="label">Role:</span>
                <span className="value capitalize">{user?.role || 'doctor'}</span>
              </div>
              <div className="info-item">
                <span className="label">License Number:</span>
                <span className="value">{user?.license_number || 'N/A'}</span>
              </div>
              <div className="info-item">
                <span className="label">Account Status:</span>
                <span className={`status-badge ${user?.is_active ? 'active' : 'inactive'}`}>
                  {user?.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default DoctorProfile;
