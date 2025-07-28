import React, { useState } from 'react';
import {
  View,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Image,
} from 'react-native';
import {
  Text,
  TextInput,
  Button,
  Card,
  Title,
  Paragraph,
  ActivityIndicator,
} from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';
import Toast from 'react-native-toast-message';
// Remove LinearGradient import as it's not in dependencies

import { useAuth } from '../context/AuthContext';
import { theme } from '../theme/theme';

export default function LoginScreen() {
  const [serverUrl, setServerUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  
  const { login } = useAuth();

  const handleLogin = async () => {
    if (!serverUrl.trim()) {
      Toast.show({
        type: 'error',
        text1: 'Server URL Required',
        text2: 'Please enter your WordPress site URL',
      });
      return;
    }

    if (!username.trim() || !password.trim()) {
      Toast.show({
        type: 'error',
        text1: 'Credentials Required',
        text2: 'Please enter both username and password',
      });
      return;
    }

    setLoading(true);

    try {
      const result = await login(serverUrl.trim(), username.trim(), password);
      
      if (!result.success) {
        Toast.show({
          type: 'error',
          text1: 'Login Failed',
          text2: result.error || 'Invalid credentials',
        });
      }
    } catch (error) {
      Toast.show({
        type: 'error',
        text1: 'Connection Error',
        text2: 'Please check your server URL and internet connection',
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.gradient}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={styles.keyboardView}
        >
          <ScrollView
            contentContainerStyle={styles.scrollContainer}
            keyboardShouldPersistTaps="handled"
          >
            <View style={styles.header}>
              <View style={styles.logoContainer}>
                <Text style={styles.logoText}>🍽️</Text>
              </View>
              <Title style={styles.title}>Restaurant Admin</Title>
              <Paragraph style={styles.subtitle}>
                Manage your table reservations
              </Paragraph>
            </View>

            <Card style={styles.card}>
              <Card.Content style={styles.cardContent}>
                <TextInput
                  label="WordPress Site URL"
                  value={serverUrl}
                  onChangeText={setServerUrl}
                  mode="outlined"
                  style={styles.input}
                  placeholder="https://yourrestaurant.com"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="url"
                  disabled={loading}
                />

                <TextInput
                  label="Username"
                  value={username}
                  onChangeText={setUsername}
                  mode="outlined"
                  style={styles.input}
                  autoCapitalize="none"
                  autoCorrect={false}
                  disabled={loading}
                />

                <TextInput
                  label="Password"
                  value={password}
                  onChangeText={setPassword}
                  mode="outlined"
                  style={styles.input}
                  secureTextEntry
                  disabled={loading}
                />

                <Button
                  mode="contained"
                  onPress={handleLogin}
                  style={styles.loginButton}
                  disabled={loading}
                  loading={loading}
                >
                  {loading ? 'Signing In...' : 'Sign In'}
                </Button>

                <View style={styles.helpContainer}>
                  <Text style={styles.helpText}>
                    Need help? Make sure your WordPress site has the Table Reservation System plugin installed and activated.
                  </Text>
                </View>
              </Card.Content>
            </Card>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  gradient: {
    flex: 1,
    backgroundColor: theme.colors.primary,
  },
  keyboardView: {
    flex: 1,
  },
  scrollContainer: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: theme.spacing.lg,
  },
  header: {
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
  },
  logoContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  logoText: {
    fontSize: 40,
  },
  title: {
    color: 'white',
    fontSize: theme.fontSize.xxl,
    fontWeight: 'bold',
    marginBottom: theme.spacing.sm,
  },
  subtitle: {
    color: 'rgba(255, 255, 255, 0.8)',
    fontSize: theme.fontSize.md,
    textAlign: 'center',
  },
  card: {
    ...theme.shadow.large,
  },
  cardContent: {
    padding: theme.spacing.xl,
  },
  input: {
    marginBottom: theme.spacing.md,
  },
  loginButton: {
    marginTop: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
  },
  helpContainer: {
    marginTop: theme.spacing.lg,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.md,
  },
  helpText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    textAlign: 'center',
    lineHeight: 20,
  },
});