import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { Provider as PaperProvider } from 'react-native-paper';
import { StatusBar } from 'expo-status-bar';
import Toast from 'react-native-toast-message';
import { Ionicons } from '@expo/vector-icons';
import * as SplashScreen from 'expo-splash-screen';
import * as SecureStore from 'expo-secure-store';

// Services
import { AuthService } from './src/services/AuthService';
import { ApiService } from './src/services/ApiService';

// Screens
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import ReservationsScreen from './src/screens/ReservationsScreen';
import ReservationDetailScreen from './src/screens/ReservationDetailScreen';
import TablesScreen from './src/screens/TablesScreen';
import SettingsScreen from './src/screens/SettingsScreen';

// Context
import { AuthContext } from './src/context/AuthContext';
import { ApiContext } from './src/context/ApiContext';

// Theme
import { theme } from './src/theme/theme';

const Tab = createBottomTabNavigator();
const Stack = createNativeStackNavigator();

// Keep splash screen visible while loading
SplashScreen.preventAutoHideAsync();

function ReservationsStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen 
        name="ReservationsList" 
        component={ReservationsScreen}
        options={{ title: 'Reservations' }}
      />
      <Stack.Screen 
        name="ReservationDetail" 
        component={ReservationDetailScreen}
        options={{ title: 'Reservation Details' }}
      />
    </Stack.Navigator>
  );
}

function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          if (route.name === 'Dashboard') {
            iconName = focused ? 'home' : 'home-outline';
          } else if (route.name === 'Reservations') {
            iconName = focused ? 'calendar' : 'calendar-outline';
          } else if (route.name === 'Tables') {
            iconName = focused ? 'restaurant' : 'restaurant-outline';
          } else if (route.name === 'Settings') {
            iconName = focused ? 'settings' : 'settings-outline';
          }

          return <Ionicons name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: 'gray',
        headerShown: false,
      })}
    >
      <Tab.Screen name="Dashboard" component={DashboardScreen} />
      <Tab.Screen name="Reservations" component={ReservationsStack} />
      <Tab.Screen name="Tables" component={TablesScreen} />
      <Tab.Screen name="Settings" component={SettingsScreen} />
    </Tab.Navigator>
  );
}

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState(null);
  const [apiService, setApiService] = useState(null);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = await SecureStore.getItemAsync('auth_token');
      const userData = await SecureStore.getItemAsync('user_data');
      const serverUrl = await SecureStore.getItemAsync('server_url');

      if (token && userData && serverUrl) {
        const parsedUser = JSON.parse(userData);
        
        // Initialize API service
        const api = new ApiService(serverUrl, token);
        
        // Verify token is still valid
        try {
          await api.getDashboard();
          
          setUser(parsedUser);
          setApiService(api);
          setIsAuthenticated(true);
        } catch (error) {
          // Token expired or invalid
          await logout();
        }
      }
    } catch (error) {
      console.error('Error checking auth status:', error);
    } finally {
      setIsLoading(false);
      await SplashScreen.hideAsync();
    }
  };

  const login = async (serverUrl, username, password) => {
    try {
      const authService = new AuthService(serverUrl);
      const response = await authService.login(username, password);

      if (response.success) {
        // Store auth data
        await SecureStore.setItemAsync('auth_token', response.token);
        await SecureStore.setItemAsync('user_data', JSON.stringify(response.user));
        await SecureStore.setItemAsync('server_url', serverUrl);

        // Initialize API service
        const api = new ApiService(serverUrl, response.token);
        
        setUser(response.user);
        setApiService(api);
        setIsAuthenticated(true);

        return { success: true };
      } else {
        return { success: false, error: 'Invalid credentials' };
      }
    } catch (error) {
      console.error('Login error:', error);
      return { 
        success: false, 
        error: error.response?.data?.message || 'Login failed' 
      };
    }
  };

  const logout = async () => {
    try {
      await SecureStore.deleteItemAsync('auth_token');
      await SecureStore.deleteItemAsync('user_data');
      await SecureStore.deleteItemAsync('server_url');

      setUser(null);
      setApiService(null);
      setIsAuthenticated(false);
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  if (isLoading) {
    return null; // Splash screen is shown
  }

  return (
    <PaperProvider theme={theme}>
      <AuthContext.Provider value={{ 
        isAuthenticated, 
        user, 
        login, 
        logout 
      }}>
        <ApiContext.Provider value={apiService}>
          <NavigationContainer>
            <StatusBar style="auto" />
            {isAuthenticated ? (
              <MainTabs />
            ) : (
              <Stack.Navigator screenOptions={{ headerShown: false }}>
                <Stack.Screen name="Login" component={LoginScreen} />
              </Stack.Navigator>
            )}
          </NavigationContainer>
          <Toast />
        </ApiContext.Provider>
      </AuthContext.Provider>
    </PaperProvider>
  );
}