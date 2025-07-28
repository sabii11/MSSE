import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import {
  Text,
  Card,
  Chip,
  Searchbar,
  Button,
  ActivityIndicator,
  Menu,
  Divider,
  FAB,
} from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { format, parseISO, isToday, isTomorrow } from 'date-fns';
import Toast from 'react-native-toast-message';

import { useApi } from '../context/ApiContext';
import { theme } from '../theme/theme';

const STATUS_FILTERS = [
  { value: '', label: 'All Statuses' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'seated', label: 'Seated' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no_show', label: 'No Show' },
];

export default function ReservationsScreen({ navigation }) {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('today');
  const [menuVisible, setMenuVisible] = useState(false);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  
  const api = useApi();

  const getDateRange = (filter) => {
    const today = new Date();
    switch (filter) {
      case 'today':
        return {
          date_from: format(today, 'yyyy-MM-dd'),
          date_to: format(today, 'yyyy-MM-dd'),
        };
      case 'tomorrow':
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        return {
          date_from: format(tomorrow, 'yyyy-MM-dd'),
          date_to: format(tomorrow, 'yyyy-MM-dd'),
        };
      case 'week':
        const weekEnd = new Date(today);
        weekEnd.setDate(weekEnd.getDate() + 7);
        return {
          date_from: format(today, 'yyyy-MM-dd'),
          date_to: format(weekEnd, 'yyyy-MM-dd'),
        };
      default:
        return {
          date_from: format(today, 'yyyy-MM-dd'),
          date_to: format(today, 'yyyy-MM-dd'),
        };
    }
  };

  const loadReservations = useCallback(async (resetList = true) => {
    try {
      const dateRange = getDateRange(dateFilter);
      const params = {
        ...dateRange,
        page: resetList ? 1 : page,
        per_page: 20,
      };

      if (statusFilter) {
        params.status = statusFilter;
      }

      const response = await api.getReservations(params);
      
      if (response.success) {
        if (resetList) {
          setReservations(response.data.reservations);
          setPage(2);
        } else {
          setReservations(prev => [...prev, ...response.data.reservations]);
          setPage(prev => prev + 1);
        }
        setHasMore(response.data.pagination.page < response.data.pagination.total_pages);
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
  }, [api, dateFilter, statusFilter, page]);

  useEffect(() => {
    setLoading(true);
    loadReservations(true);
  }, [dateFilter, statusFilter]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadReservations(true);
  }, [loadReservations]);

  const loadMore = () => {
    if (!loading && hasMore) {
      loadReservations(false);
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

  const getDateLabel = (dateString) => {
    try {
      const date = parseISO(dateString);
      if (isToday(date)) return 'Today';
      if (isTomorrow(date)) return 'Tomorrow';
      return format(date, 'MMM d');
    } catch {
      return dateString;
    }
  };

  const filteredReservations = reservations.filter(reservation =>
    searchQuery === '' ||
    reservation.customer_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    reservation.customer_email.toLowerCase().includes(searchQuery.toLowerCase()) ||
    reservation.table_number.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const renderReservation = ({ item }) => (
    <TouchableOpacity
      onPress={() => navigation.navigate('ReservationDetail', { 
        reservationId: item.id,
        reservation: item 
      })}
    >
      <Card style={styles.reservationCard}>
        <Card.Content style={styles.cardContent}>
          <View style={styles.reservationHeader}>
            <View style={styles.timeContainer}>
              <Text style={styles.dateText}>{getDateLabel(item.reservation_date)}</Text>
              <Text style={styles.timeText}>{formatTime(item.reservation_time)}</Text>
            </View>
            <Chip
              style={[styles.statusChip, { backgroundColor: getStatusColor(item.status) }]}
              textStyle={styles.statusChipText}
            >
              {item.status_label}
            </Chip>
          </View>
          
          <Text style={styles.customerName}>{item.customer_name}</Text>
          <Text style={styles.partyInfo}>
            {item.party_size} {item.party_size === 1 ? 'person' : 'people'} • Table {item.table_number}
          </Text>
          
          {item.special_request && (
            <Text style={styles.specialRequest} numberOfLines={2}>
              Note: {item.special_request}
            </Text>
          )}
          
          {item.time_until && (
            <Text style={styles.timeUntil}>
              <Ionicons name="time-outline" size={14} color={theme.colors.warning} />
              {' '}In {item.time_until}
            </Text>
          )}
        </Card.Content>
      </Card>
    </TouchableOpacity>
  );

  const renderHeader = () => (
    <View style={styles.header}>
      <Searchbar
        placeholder="Search reservations..."
        onChangeText={setSearchQuery}
        value={searchQuery}
        style={styles.searchbar}
      />
      
      <View style={styles.filterContainer}>
        <Button
          mode={dateFilter === 'today' ? 'contained' : 'outlined'}
          onPress={() => setDateFilter('today')}
          compact
          style={styles.filterButton}
        >
          Today
        </Button>
        <Button
          mode={dateFilter === 'tomorrow' ? 'contained' : 'outlined'}
          onPress={() => setDateFilter('tomorrow')}
          compact
          style={styles.filterButton}
        >
          Tomorrow
        </Button>
        <Button
          mode={dateFilter === 'week' ? 'contained' : 'outlined'}
          onPress={() => setDateFilter('week')}
          compact
          style={styles.filterButton}
        >
          This Week
        </Button>
        
        <Menu
          visible={menuVisible}
          onDismiss={() => setMenuVisible(false)}
          anchor={
            <Button
              mode="outlined"
              onPress={() => setMenuVisible(true)}
              compact
              style={styles.filterButton}
              icon="filter"
            >
              {STATUS_FILTERS.find(f => f.value === statusFilter)?.label || 'Status'}
            </Button>
          }
        >
          {STATUS_FILTERS.map((filter) => (
            <Menu.Item
              key={filter.value}
              onPress={() => {
                setStatusFilter(filter.value);
                setMenuVisible(false);
              }}
              title={filter.label}
            />
          ))}
        </Menu>
      </View>
    </View>
  );

  const renderEmpty = () => (
    <View style={styles.emptyState}>
      <Ionicons name="calendar-outline" size={64} color={theme.colors.disabled} />
      <Text style={styles.emptyStateTitle}>No reservations found</Text>
      <Text style={styles.emptyStateText}>
        {searchQuery ? 'Try adjusting your search' : 'No reservations for the selected period'}
      </Text>
    </View>
  );

  const renderFooter = () => {
    if (!loading || refreshing) return null;
    
    return (
      <View style={styles.loadingFooter}>
        <ActivityIndicator size="small" color={theme.colors.primary} />
      </View>
    );
  };

  if (loading && !refreshing) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Loading reservations...</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <FlatList
        data={filteredReservations}
        renderItem={renderReservation}
        keyExtractor={(item) => item.id.toString()}
        ListHeaderComponent={renderHeader}
        ListEmptyComponent={renderEmpty}
        ListFooterComponent={renderFooter}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        onEndReached={loadMore}
        onEndReachedThreshold={0.1}
        contentContainerStyle={styles.listContent}
      />
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
  listContent: {
    paddingBottom: theme.spacing.xl,
  },
  header: {
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
  },
  searchbar: {
    marginBottom: theme.spacing.md,
  },
  filterContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  filterButton: {
    minWidth: 80,
  },
  reservationCard: {
    margin: theme.spacing.md,
    marginTop: 0,
    ...theme.shadow.small,
  },
  cardContent: {
    paddingVertical: theme.spacing.md,
  },
  reservationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.md,
  },
  timeContainer: {
    flex: 1,
  },
  dateText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.placeholder,
    marginBottom: theme.spacing.xs,
  },
  timeText: {
    fontSize: theme.fontSize.lg,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  statusChip: {
    height: 28,
  },
  statusChipText: {
    fontSize: theme.fontSize.xs,
    color: 'white',
  },
  customerName: {
    fontSize: theme.fontSize.lg,
    fontWeight: '600',
    marginBottom: theme.spacing.xs,
  },
  partyInfo: {
    fontSize: theme.fontSize.md,
    color: theme.colors.placeholder,
    marginBottom: theme.spacing.sm,
  },
  specialRequest: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    fontStyle: 'italic',
    marginBottom: theme.spacing.sm,
    lineHeight: 18,
  },
  timeUntil: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.warning,
    alignItems: 'center',
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: theme.spacing.xl * 2,
    paddingHorizontal: theme.spacing.lg,
  },
  emptyStateTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: '600',
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.sm,
    color: theme.colors.text,
  },
  emptyStateText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.disabled,
    textAlign: 'center',
    lineHeight: 20,
  },
  loadingFooter: {
    paddingVertical: theme.spacing.lg,
    alignItems: 'center',
  },
});