import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import './Login.css';

const Login = () => {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await login(email, password);
      // Redirect based on role - response contains user and token
      const role = response.user.role;
      if (role === 'admin') {
        window.location.href = '/admin/dashboard';
      } else if (role === 'doctor') {
        window.location.href = '/doctor/dashboard';
      } else {
        window.location.href = '/patient/dashboard';
      }
    } catch (err) {
      setError(err.message || 'Login failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-card">
        <div className="login-header">
          <h1>Welcome Back</h1>
          <p>Sign in to continue to Healthcare Portal</p>
        </div>

        <form onSubmit={handleSubmit} className="login-form">
          {error && <div className="error-message">{error}</div>}

          <div className="form-group">
            <label htmlFor="email">Email Address</label>
            <input
              type="email"
              id="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Enter your email"
              required
            />
          </div>

          <div className="form-group">
            <label htmlFor="password">Password</label>
            <input
              type="password"
              id="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Enter your password"
              required
            />
          </div>

          <div className="form-options">
            <label className="remember-me">
              <input type="checkbox" />
              <span>Remember me</span>
            </label>
            <a href="/forgot-password" className="forgot-password">
              Forgot password?
            </a>
          </div>

          <button type="submit" className="login-button" disabled={loading}>
            {loading ? 'Signing in...' : 'Sign In'}
          </button>
        </form>

        <div className="login-footer">
          <p>
            Don't have an account?{' '}
            <a href="/register" className="register-link">Register</a>
          </p>
        </div>

        <div className="demo-accounts">
          <p className="demo-title">Demo Accounts:</p>
          <div className="demo-buttons">
            <button
              type="button"
              onClick={() => { setEmail('patient@demo.com'); setPassword('password'); }}
              className="demo-button patient"
            >
              Patient
            </button>
            <button
              type="button"
              onClick={() => { setEmail('doctor@demo.com'); setPassword('password'); }}
              className="demo-button doctor"
            >
              Doctor
            </button>
            <button
              type="button"
              onClick={() => { setEmail('admin@demo.com'); setPassword('password'); }}
              className="demo-button admin"
            >
              Admin
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;
