import axios from 'axios';

export class ApiService {
  constructor(serverUrl, token) {
    this.serverUrl = serverUrl.replace(/\/$/, ''); // Remove trailing slash
    this.baseURL = `${this.serverUrl}/wp-json/trs/v1`;
    this.token = token;
    
    // Create axios instance with default config
    this.client = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
    });

    // Add response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Token expired or invalid
          console.log('Token expired, need to re-authenticate');
        }
        return Promise.reject(error);
      }
    );
  }

  // Dashboard endpoints
  async getDashboard() {
    const response = await this.client.get('/dashboard');
    return response.data;
  }

  // Reservation endpoints
  async getReservations(params = {}) {
    const response = await this.client.get('/reservations', { params });
    return response.data;
  }

  async getReservation(id) {
    const response = await this.client.get(`/reservations/${id}`);
    return response.data;
  }

  async updateReservationStatus(id, status) {
    const response = await this.client.put(`/reservations/${id}/status`, {
      status,
    });
    return response.data;
  }

  // Table endpoints
  async getTables() {
    const response = await this.client.get('/tables');
    return response.data;
  }

  // Settings endpoints
  async getSettings() {
    const response = await this.client.get('/settings');
    return response.data;
  }

  async toggleBookings() {
    const response = await this.client.post('/settings/bookings/toggle');
    return response.data;
  }

  // Utility methods
  formatError(error) {
    if (error.response?.data?.message) {
      return error.response.data.message;
    } else if (error.message) {
      return error.message;
    } else {
      return 'An unexpected error occurred';
    }
  }
}