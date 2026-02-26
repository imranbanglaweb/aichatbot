import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import Sidebar from '../components/Sidebar';
import api from '../services/api';
import './DashboardLayout.css';

const DashboardLayout = ({ children, title }) => {
  const { user, isPatient, isDoctor, isAdmin } = useAuth();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [menus, setMenus] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSidebarMenu();
  }, []);

  const fetchSidebarMenu = async () => {
    try {
      const response = await api.getSidebarMenu();
      setMenus(response.data.menus);
    } catch (error) {
      console.error('Error fetching sidebar menu:', error);
      // Set default menus based on role
      if (isPatient) {
        setMenus([
          { id: 'dashboard', title: 'Dashboard', icon: 'home', path: '/patient/dashboard' },
          { id: 'appointments', title: 'My Appointments', icon: 'calendar', path: '/patient/appointments' },
          { id: 'doctors', title: 'Find Doctors', icon: 'users', path: '/patient/doctors' },
          { id: 'chatbot', title: 'AI Assistant', icon: 'message-circle', path: '/patient/chat' },
          { id: 'profile', title: 'My Profile', icon: 'user', path: '/patient/profile' },
        ]);
      } else if (isDoctor) {
        setMenus([
          { id: 'dashboard', title: 'Dashboard', icon: 'home', path: '/doctor/dashboard' },
          { id: 'appointments', title: 'Appointments', icon: 'calendar', path: '/doctor/appointments' },
          { id: 'schedule', title: 'My Schedule', icon: 'clock', path: '/doctor/schedule' },
          { id: 'patients', title: 'Patients', icon: 'users', path: '/doctor/patients' },
          { id: 'chatbot', title: 'AI Assistant', icon: 'message-circle', path: '/doctor/chat' },
          { id: 'profile', title: 'My Profile', icon: 'user', path: '/doctor/profile' },
        ]);
      } else if (isAdmin) {
        setMenus([
          { id: 'dashboard', title: 'Dashboard', icon: 'home', path: '/admin/dashboard' },
          { id: 'users', title: 'Users', icon: 'users', path: '/admin/users' },
          { id: 'doctors', title: 'Doctors', icon: 'user-md', path: '/admin/doctors' },
          { id: 'appointments', title: 'Appointments', icon: 'calendar', path: '/admin/appointments' },
          { id: 'reports', title: 'Reports', icon: 'bar-chart', path: '/admin/reports' },
        ]);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="dashboard-layout">
      <Sidebar 
        menus={menus} 
        isOpen={sidebarOpen} 
        onClose={() => setSidebarOpen(false)} 
      />
      
      <div className="dashboard-main">
        <header className="dashboard-header">
          <button 
            className="menu-toggle"
            onClick={() => setSidebarOpen(true)}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          
          <h1 className="dashboard-title">{title}</h1>
          
          <div className="header-actions">
            <button className="notification-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <span className="notification-badge">3</span>
            </button>
            
            <div className="user-menu">
              <div className="user-avatar-small">
                {user?.profile_image ? (
                  <img src={user.profile_image} alt={user.name} />
                ) : (
                  <span>{user?.name?.charAt(0) || 'U'}</span>
                )}
              </div>
              <span className="user-name">{user?.name || 'User'}</span>
            </div>
          </div>
        </header>
        
        <main className="dashboard-content">
          {children}
        </main>
      </div>
    </div>
  );
};

export default DashboardLayout;
