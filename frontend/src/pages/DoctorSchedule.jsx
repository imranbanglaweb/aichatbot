import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';

const DoctorSchedule = () => {
  const { user } = useAuth();
  const [schedule, setSchedule] = useState([
    { day: 'Monday', isActive: true, startTime: '09:00', endTime: '17:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Tuesday', isActive: true, startTime: '09:00', endTime: '17:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Wednesday', isActive: true, startTime: '09:00', endTime: '17:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Thursday', isActive: true, startTime: '09:00', endTime: '17:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Friday', isActive: true, startTime: '09:00', endTime: '17:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Saturday', isActive: false, startTime: '09:00', endTime: '14:00', breakStart: '12:00', breakEnd: '13:00' },
    { day: 'Sunday', isActive: false, startTime: '09:00', endTime: '14:00', breakStart: '12:00', breakEnd: '13:00' },
  ]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });

  const handleToggleDay = (index) => {
    const newSchedule = [...schedule];
    newSchedule[index].isActive = !newSchedule[index].isActive;
    setSchedule(newSchedule);
  };

  const handleTimeChange = (index, field, value) => {
    const newSchedule = [...schedule];
    newSchedule[index][field] = value;
    setSchedule(newSchedule);
  };

  const handleSave = async () => {
    setLoading(true);
    setMessage({ type: '', text: '' });
    
    // Simulate API call
    setTimeout(() => {
      setMessage({ type: 'success', text: 'Schedule updated successfully!' });
      setLoading(false);
    }, 1000);
  };

  return (
    <DashboardLayout title="My Schedule">
      <div className="page-container">
        {message.text && (
          <div className={`message ${message.type}`}>
            {message.text}
          </div>
        )}

        <div className="schedule-intro">
          <h3>Weekly Schedule</h3>
          <p>Set your available days and working hours for appointments</p>
        </div>

        <div className="schedule-grid">
          {schedule.map((day, index) => (
            <div key={day.day} className={`schedule-day ${day.isActive ? 'active' : 'inactive'}`}>
              <div className="day-header">
                <label className="checkbox-label">
                  <input
                    type="checkbox"
                    checked={day.isActive}
                    onChange={() => handleToggleDay(index)}
                  />
                  <span className="day-name">{day.day}</span>
                </label>
              </div>
              
              {day.isActive && (
                <div className="day-times">
                  <div className="time-group">
                    <label>Start Time</label>
                    <input
                      type="time"
                      value={day.startTime}
                      onChange={(e) => handleTimeChange(index, 'startTime', e.target.value)}
                    />
                  </div>
                  
                  <div className="time-group">
                    <label>End Time</label>
                    <input
                      type="time"
                      value={day.endTime}
                      onChange={(e) => handleTimeChange(index, 'endTime', e.target.value)}
                    />
                  </div>
                  
                  <div className="time-group">
                    <label>Break Start</label>
                    <input
                      type="time"
                      value={day.breakStart}
                      onChange={(e) => handleTimeChange(index, 'breakStart', e.target.value)}
                    />
                  </div>
                  
                  <div className="time-group">
                    <label>Break End</label>
                    <input
                      type="time"
                      value={day.breakEnd}
                      onChange={(e) => handleTimeChange(index, 'breakEnd', e.target.value)}
                    />
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>

        <div className="schedule-actions">
          <button className="btn btn-primary" onClick={handleSave} disabled={loading}>
            {loading ? 'Saving...' : 'Save Schedule'}
          </button>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default DoctorSchedule;
