import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import {
  Text,
  Card,
  Button,
  Chip,
  Title,
  Paragraph,
  ActivityIndicator,
  Switch,
} from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { format, isToday, parseISO } from 'date-fns';
import Toast from 'react-native-toast-message';

import { useApi } from '../context/ApiContext';
import { useAuth } from '../context/AuthContext';
import { theme } from '../theme/theme';

export default function DashboardScreen({ navigation }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [bookingToggling, setBookingToggling] = useState(false);
  
  const api = useApi();
  const { user } = useAuth();

  const loadData = useCallback(async () => {
    try {
      const response = await api.getDashboard();
      if (response.success) {
        setData(response.data);
      }
    } catch (error) {
      Toast.show({
        type: 'error',
        text1: 'Error',
        text2: api.formatError(error),
      });
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [api]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadData();
  }, [loadData]);

  const toggleBookings = async () => {
    setBookingToggling(true);
    try {
      const response = await api.toggleBookings();
      if (response.success) {
        setData(prevData => ({
          ...prevData,
          booking_status: response.data.booking_status,
        }));
        Toast.show({
          type: 'success',
          text1: 'Success',
          text2: response.message,
        });
      }
    } catch (error) {
      Toast.show({
        type: 'error',
        text1: 'Error',
        text2: api.formatError(error),
      });
    } finally {
      setBookingToggling(false);
    }
  };

  const getStatusColor = (status) => {
    return theme.colors[status] || theme.colors.text;
  };

  const formatTime = (timeString) => {
    try {
      const [hours, minutes] = timeString.split(':');
      const date = new Date();
      date.setHours(parseInt(hours), parseInt(minutes));
      return format(date, 'h:mm a');
    } catch {
      return timeString;
    }
  };

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Loading dashboard...</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <Title style={styles.headerTitle}>
            Welcome back, {user?.display_name || user?.username}!
          </Title>
          <Paragraph style={styles.headerSubtitle}>
            {data?.restaurant_name || 'Restaurant'} Dashboard
          </Paragraph>
        </View>

        {/* Statistics Cards */}
        <View style={styles.statsContainer}>
          <Card style={[styles.statCard, { backgroundColor: theme.colors.primary }]}>
            <Card.Content style={styles.statContent}>
              <Text style={styles.statNumber}>{data?.stats?.today_reservations || 0}</Text>
              <Text style={styles.statLabel}>Today</Text>
            </Card.Content>
          </Card>
          
          <Card style={[styles.statCard, { backgroundColor: theme.colors.success }]}>
            <Card.Content style={styles.statContent}>
              <Text style={styles.statNumber}>{data?.stats?.week_reservations || 0}</Text>
              <Text style={styles.statLabel}>This Week</Text>
            </Card.Content>
          </Card>
          
          <Card style={[styles.statCard, { backgroundColor: theme.colors.warning }]}>
            <Card.Content style={styles.statContent}>
              <Text style={styles.statNumber}>{data?.stats?.month_reservations || 0}</Text>
              <Text style={styles.statLabel}>This Month</Text>
            </Card.Content>
          </Card>
          
          <Card style={[styles.statCard, { backgroundColor: theme.colors.error }]}>
            <Card.Content style={styles.statContent}>
              <Text style={styles.statNumber}>{data?.stats?.pending_confirmations || 0}</Text>
              <Text style={styles.statLabel}>Pending</Text>
            </Card.Content>
          </Card>
        </View>

        {/* Booking Control */}
        <Card style={styles.card}>
          <Card.Content>
            <View style={styles.bookingControlHeader}>
              <View>
                <Title style={styles.cardTitle}>Booking Status</Title>
                <Paragraph>
                  Currently {data?.booking_status ? 'accepting' : 'not accepting'} reservations
                </Paragraph>
              </View>
              <Switch
                value={data?.booking_status || false}
                onValueChange={toggleBookings}
                disabled={bookingToggling}
              />
            </View>
          </Card.Content>
        </Card>

        {/* Upcoming Reservations */}
        {data?.upcoming_reservations?.length > 0 && (
          <Card style={styles.card}>
            <Card.Content>
              <Title style={styles.cardTitle}>Upcoming (Next 2 Hours)</Title>
              {data.upcoming_reservations.map((reservation) => (
                <TouchableOpacity
                  key={reservation.id}
                  style={styles.reservationItem}
                  onPress={() => navigation.navigate('Reservations', {
                    screen: 'ReservationDetail',
                    params: { reservationId: reservation.id }
                  })}
                >
                  <View style={styles.reservationHeader}>
                    <Text style={styles.reservationTime}>
                      {formatTime(reservation.reservation_time)}
                    </Text>
                    <Chip
                      style={[styles.statusChip, { backgroundColor: getStatusColor(reservation.status) }]}
                      textStyle={styles.statusChipText}
                    >
                      {reservation.status_label}
                    </Chip>
                  </View>
                  <Text style={styles.reservationCustomer}>
                    {reservation.customer_name} - {reservation.party_size} people
                  </Text>
                  <Text style={styles.reservationTable}>
                    Table {reservation.table_number}
                  </Text>
                </TouchableOpacity>
              ))}
            </Card.Content>
          </Card>
        )}

        {/* Today's Reservations */}
        <Card style={styles.card}>
          <Card.Content>
            <View style={styles.cardHeader}>
              <Title style={styles.cardTitle}>Today's Reservations</Title>
              <Button
                mode="outlined"
                onPress={() => navigation.navigate('Reservations')}
                compact
              >
                View All
              </Button>
            </View>
            
            {data?.today_reservations?.length === 0 ? (
              <View style={styles.emptyState}>
                <Ionicons 
                  name="calendar-outline" 
                  size={48} 
                  color={theme.colors.disabled} 
                />
                <Text style={styles.emptyStateText}>No reservations today</Text>
              </View>
            ) : (
              data?.today_reservations?.slice(0, 5).map((reservation) => (
                <TouchableOpacity
                  key={reservation.id}
                  style={styles.reservationItem}
                  onPress={() => navigation.navigate('Reservations', {
                    screen: 'ReservationDetail',
                    params: { reservationId: reservation.id }
                  })}
                >
                  <View style={styles.reservationHeader}>
                    <Text style={styles.reservationTime}>
                      {formatTime(reservation.reservation_time)}
                    </Text>
                    <Chip
                      style={[styles.statusChip, { backgroundColor: getStatusColor(reservation.status) }]}
                      textStyle={styles.statusChipText}
                    >
                      {reservation.status_label}
                    </Chip>
                  </View>
                  <Text style={styles.reservationCustomer}>
                    {reservation.customer_name} - {reservation.party_size} people
                  </Text>
                  <Text style={styles.reservationTable}>
                    Table {reservation.table_number}
                  </Text>
                  {reservation.time_until && (
                    <Text style={styles.timeUntil}>
                      In {reservation.time_until}
                    </Text>
                  )}
                </TouchableOpacity>
              ))
            )}
          </Card.Content>
        </Card>

        {/* Quick Actions */}
        <Card style={styles.card}>
          <Card.Content>
            <Title style={styles.cardTitle}>Quick Actions</Title>
            <View style={styles.quickActionsContainer}>
              <Button
                mode="contained"
                onPress={() => navigation.navigate('Reservations')}
                style={styles.quickActionButton}
                icon="calendar"
              >
                View Reservations
              </Button>
              <Button
                mode="outlined"
                onPress={() => navigation.navigate('Tables')}
                style={styles.quickActionButton}
                icon="restaurant"
              >
                Manage Tables
              </Button>
            </View>
          </Card.Content>
        </Card>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: theme.spacing.md,
    color: theme.colors.text,
  },
  scrollView: {
    flex: 1,
  },
  header: {
    padding: theme.spacing.lg,
    backgroundColor: theme.colors.surface,
  },
  headerTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: 'bold',
  },
  headerSubtitle: {
    color: theme.colors.placeholder,
    marginTop: theme.spacing.xs,
  },
  statsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: theme.spacing.md,
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    marginBottom: theme.spacing.md,
    ...theme.shadow.medium,
  },
  statContent: {
    alignItems: 'center',
    paddingVertical: theme.spacing.lg,
  },
  statNumber: {
    fontSize: theme.fontSize.xxl,
    fontWeight: 'bold',
    color: 'white',
  },
  statLabel: {
    fontSize: theme.fontSize.sm,
    color: 'rgba(255, 255, 255, 0.8)',
    marginTop: theme.spacing.xs,
  },
  card: {
    margin: theme.spacing.md,
    marginTop: 0,
    ...theme.shadow.medium,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  cardTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: '600',
  },
  bookingControlHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  reservationItem: {
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.background,
  },
  reservationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  reservationTime: {
    fontSize: theme.fontSize.md,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  statusChip: {
    height: 24,
  },
  statusChipText: {
    fontSize: theme.fontSize.xs,
    color: 'white',
  },
  reservationCustomer: {
    fontSize: theme.fontSize.md,
    fontWeight: '500',
    marginBottom: theme.spacing.xs,
  },
  reservationTable: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.placeholder,
  },
  timeUntil: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.warning,
    fontStyle: 'italic',
    marginTop: theme.spacing.xs,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: theme.spacing.xl,
  },
  emptyStateText: {
    marginTop: theme.spacing.md,
    color: theme.colors.disabled,
    fontSize: theme.fontSize.md,
  },
  quickActionsContainer: {
    gap: theme.spacing.md,
  },
  quickActionButton: {
    marginBottom: theme.spacing.sm,
  },
});