import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import api from '../services/api';

const AdminUsers = () => {
  const { user } = useAuth();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [roleFilter, setRoleFilter] = useState('');

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      const response = await api.getMe();
      // Would need a dedicated endpoint for admin to get all users
      // For now, show mock data or fetch from available endpoints
      setUsers([
        { id: 1, name: 'John Patient', email: 'patient@demo.com', phone: '+1234567001', role: 'patient', is_active: true, created_at: '2024-01-15' },
        { id: 2, name: 'Dr. Sarah Smith', email: 'doctor1@demo.com', phone: '+1234567002', role: 'doctor', is_active: true, created_at: '2024-01-10' },
        { id: 3, name: 'Admin User', email: 'admin@demo.com', phone: '+1234567003', role: 'admin', is_active: true, created_at: '2024-01-01' },
      ]);
    } catch (err) {
      setError('Failed to load users');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleToggleStatus = async (userId) => {
    // Would need API endpoint
    alert('User status toggled');
  };

  const filteredUsers = users.filter(u => {
    const matchesSearch = u.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          u.email?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesRole = !roleFilter || u.role === roleFilter;
    return matchesSearch && matchesRole;
  });

  if (loading) {
    return (
      <DashboardLayout title="Manage Users">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Manage Users">
      <div className="page-container">
        <div className="page-header">
          <h3>All Users</h3>
          <div className="header-actions">
            <div className="search-box">
              <input
                type="text"
                placeholder="Search users..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
            >
              <option value="">All Roles</option>
              <option value="patient">Patients</option>
              <option value="doctor">Doctors</option>
              <option value="admin">Admins</option>
            </select>
          </div>
        </div>

        {error && <div className="error-message">{error}</div>}

        {filteredUsers.length > 0 ? (
          <div className="users-table">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Joined</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredUsers.map((u) => (
                  <tr key={u.id}>
                    <td>{u.id}</td>
                    <td>{u.name}</td>
                    <td>{u.email}</td>
                    <td>{u.phone}</td>
                    <td>
                      <span className={`role-badge role-${u.role}`}>
                        {u.role}
                      </span>
                    </td>
                    <td>
                      <span className={`status-badge ${u.is_active ? 'active' : 'inactive'}`}>
                        {u.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>{u.created_at}</td>
                    <td>
                      <div className="action-buttons">
                        <button className="btn btn-sm btn-primary">View</button>
                        <button 
                          className="btn btn-sm btn-secondary"
                          onClick={() => handleToggleStatus(u.id)}
                        >
                          Toggle Status
                        </button>
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
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
            </svg>
            <h4>No Users Found</h4>
            <p>No users match your search criteria</p>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
};

export default AdminUsers;
