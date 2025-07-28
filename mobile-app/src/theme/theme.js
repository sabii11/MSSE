import { DefaultTheme } from 'react-native-paper';

export const theme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    primary: '#667eea',
    accent: '#764ba2',
    background: '#f8f9fa',
    surface: '#ffffff',
    text: '#1d2327',
    disabled: '#c3c4c7',
    placeholder: '#646970',
    backdrop: 'rgba(0, 0, 0, 0.5)',
    
    // Status colors
    success: '#00a32a',
    warning: '#dba617',
    error: '#d63638',
    info: '#2271b1',
    
    // Reservation status colors
    confirmed: '#2196f3',
    seated: '#4caf50',
    completed: '#8bc34a',
    cancelled: '#f44336',
    no_show: '#ff9800',
    pending: '#ffc107',
  },
  
  // Custom spacing
  spacing: {
    xs: 4,
    sm: 8,
    md: 16,
    lg: 24,
    xl: 32,
  },
  
  // Custom font sizes
  fontSize: {
    xs: 12,
    sm: 14,
    md: 16,
    lg: 18,
    xl: 24,
    xxl: 32,
  },
  
  // Shadows
  shadow: {
    small: {
      shadowColor: '#000',
      shadowOffset: {
        width: 0,
        height: 1,
      },
      shadowOpacity: 0.18,
      shadowRadius: 1.0,
      elevation: 1,
    },
    medium: {
      shadowColor: '#000',
      shadowOffset: {
        width: 0,
        height: 2,
      },
      shadowOpacity: 0.20,
      shadowRadius: 2.5,
      elevation: 3,
    },
    large: {
      shadowColor: '#000',
      shadowOffset: {
        width: 0,
        height: 4,
      },
      shadowOpacity: 0.25,
      shadowRadius: 4.0,
      elevation: 6,
    },
  },
  
  // Border radius
  borderRadius: {
    sm: 4,
    md: 8,
    lg: 12,
    xl: 16,
    round: 50,
  },
};