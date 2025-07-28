import axios from 'axios';

export class AuthService {
  constructor(serverUrl) {
    this.serverUrl = serverUrl.replace(/\/$/, ''); // Remove trailing slash
    this.baseURL = `${this.serverUrl}/wp-json/trs/v1`;
  }

  async login(username, password) {
    try {
      const response = await axios.post(`${this.baseURL}/auth`, {
        username,
        password,
      });

      return response.data;
    } catch (error) {
      console.error('Auth service error:', error);
      throw error;
    }
  }

  validateServerUrl(url) {
    // Basic URL validation
    const urlPattern = /^https?:\/\/.+/;
    return urlPattern.test(url);
  }
}