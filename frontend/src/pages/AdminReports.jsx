import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';

const AdminReports = () => {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [reportType, setReportType] = useState('overview');

  useEffect(() => {
    // Simulate loading
    setTimeout(() => setLoading(false), 500);
  }, []);

  const reportData = {
    overview: {
      title: 'System Overview',
      stats: [
        { label: 'Total Users', value: '156', change: '+12%', icon: 'users' },
        { label: 'Total Doctors', value: '24', change: '+5%', icon: 'doctors' },
        { label: 'Total Appointments', value: '1,234', change: '+18%', icon: 'appointments' },
        { label: 'Revenue', value: '৳245,000', change: '+25%', icon: 'revenue' },
      ]
    },
    appointments: {
      title: 'Appointment Reports',
      charts: [
        { label: 'This Month', value: '234' },
        { label: 'Last Month', value: '198' },
        { label: 'This Year', value: '1,234' },
      ]
    },
    revenue: {
      title: 'Revenue Reports',
      charts: [
        { label: 'Consultation Fees', value: '৳180,000' },
        { label: 'Other Services', value: '৳65,000' },
        { label: 'Total Revenue', value: '৳245,000' },
      ]
    }
  };

  if (loading) {
    return (
      <DashboardLayout title="Reports">
        <div className="loading">Loading...</div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout title="Reports">
      <div className="page-container">
        <div className="report-header">
          <h3>Analytics & Reports</h3>
          <div className="report-type-selector">
            <button 
              className={`report-btn ${reportType === 'overview' ? 'active' : ''}`}
              onClick={() => setReportType('overview')}
            >
              Overview
            </button>
            <button 
              className={`report-btn ${reportType === 'appointments' ? 'active' : ''}`}
              onClick={() => setReportType('appointments')}
            >
              Appointments
            </button>
            <button 
              className={`report-btn ${reportType === 'revenue' ? 'active' : ''}`}
              onClick={() => setReportType('revenue')}
            >
              Revenue
            </button>
          </div>
        </div>

        <div className="report-content">
          <h4>{reportData[reportType].title}</h4>
          
          {reportType === 'overview' && (
            <div className="stats-grid">
              {reportData.overview.stats.map((stat, index) => (
                <div key={index} className="stat-card report-stat">
                  <div className="stat-info">
                    <span className="stat-label">{stat.label}</span>
                    <span className="stat-value">{stat.value}</span>
                    <span className="stat-change positive">{stat.change} from last month</span>
                  </div>
                </div>
              ))}
            </div>
          )}

          {reportType === 'appointments' && (
            <div className="report-charts">
              {reportData.appointments.charts.map((chart, index) => (
                <div key={index} className="chart-card">
                  <span className="chart-label">{chart.label}</span>
                  <span className="chart-value">{chart.value}</span>
                </div>
              ))}
            </div>
          )}

          {reportType === 'revenue' && (
            <div className="report-charts">
              {reportData.revenue.charts.map((chart, index) => (
                <div key={index} className="chart-card">
                  <span className="chart-label">{chart.label}</span>
                  <span className="chart-value">{chart.value}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="export-section">
          <h4>Export Reports</h4>
          <div className="export-buttons">
            <button className="btn btn-primary">Export PDF</button>
            <button className="btn btn-secondary">Export Excel</button>
            <button className="btn btn-secondary">Print Report</button>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
};

export default AdminReports;
