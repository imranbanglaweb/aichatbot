import config from '../config';

class ApiService {
  constructor() {
    this.baseUrl = config.api.baseUrl;
  }

  getHeaders() {
    const token = localStorage.getItem('token');
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    };
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const config = {
      ...options,
      headers: {
        ...this.getHeaders(),
        ...options.headers,
      },
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Something went wrong');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Auth endpoints
  async login(email, password) {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
  }

  async register(userData) {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify(userData),
    });
  }

  async logout() {
    return this.request('/auth/logout', {
      method: 'POST',
    });
  }

  async getMe() {
    return this.request('/auth/me');
  }

  async updateProfile(data) {
    return this.request('/auth/profile', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async changePassword(data) {
    return this.request('/auth/password', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  // Dashboard endpoints
  async getPatientDashboard() {
    return this.request('/dashboard/patient');
  }

  async getDoctorDashboard() {
    return this.request('/dashboard/doctor');
  }

  async getAdminDashboard() {
    return this.request('/dashboard/admin');
  }

  async getSidebarMenu() {
    return this.request('/dashboard/sidebar');
  }

  // Doctor endpoints
  async getDoctors(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const url = queryString ? `/doctors/available?${queryString}` : '/doctors/available';
    return this.request(url);
  }

  async getDoctor(id) {
    return this.request(`/doctors/${id}`);
  }

  async getDoctorSlots(id, date) {
    const queryString = date ? `?date=${date}` : '';
    return this.request(`/doctors/${id}/slots${queryString}`);
  }

  async getSpecializations() {
    return this.request('/specializations');
  }

  // Appointment endpoints
  async bookAppointment(data) {
    return this.request('/appointment/book', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async cancelAppointment(appointmentNumber) {
    return this.request('/appointment/cancel', {
      method: 'POST',
      body: JSON.stringify({ appointment_number: appointmentNumber }),
    });
  }

  async getAppointments() {
    return this.request('/appointments');
  }

  // Chat endpoints
  async sendMessage(sessionId, message) {
    return this.request('/chat/message', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId, message }),
    });
  }

  // Speech-to-text transcription (for voice input to text)
  async transcribeAudio(audioFile, language = 'en') {
    const formData = new FormData();
    formData.append('audio', audioFile);
    formData.append('language', language);

    const token = localStorage.getItem('token');
    const url = `${this.baseUrl}/chat/transcribe`;

    try {
      const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to transcribe audio');
      }

      return data;
    } catch (error) {
      console.error('Transcription Error:', error);
      throw error;
    }
  }

  async getChatHistory(sessionId) {
    return this.request(`/chat/history/${sessionId}`);
  }
}

export default new ApiService();
