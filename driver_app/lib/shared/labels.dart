import 'package:flutter/material.dart';

import '../core/l10n/gen/app_localizations.dart';
import '../data/models/driver_profile.dart';

const vehicleTypes = ['boda_boda', 'bajaj', 'car', 'bicycle'];

String vehicleLabel(AppLocalizations l, String type) => switch (type) {
      'boda_boda' => l.vehicleBodaBoda,
      'bajaj' => l.vehicleBajaj,
      'car' => l.vehicleCar,
      'bicycle' => l.vehicleBicycle,
      _ => type,
    };

IconData vehicleIcon(String type) => switch (type) {
      'bajaj' => Icons.electric_rickshaw_outlined,
      'car' => Icons.directions_car_outlined,
      'bicycle' => Icons.pedal_bike_outlined,
      _ => Icons.two_wheeler_outlined,
    };

String jobStatusLabel(AppLocalizations l, String status) => switch (status) {
      'pending_payment' => l.statusPendingPayment,
      'pending_driver' => l.statusPendingDriver,
      'no_driver_found' => l.statusNoDriverFound,
      'driver_assigned' => l.statusDriverAssigned,
      'arrived_at_shop' => l.statusArrivedAtShop,
      'picked_up' => l.statusPickedUp,
      'on_the_way' => l.statusOnTheWay,
      'arrived' => l.statusArrived,
      'delivered' => l.statusDelivered,
      'cancelled' => l.statusCancelled,
      'failed' => l.statusFailed,
      _ => status,
    };

String requirementLabel(AppLocalizations l, String key) => switch (key) {
      'photo' => l.reqPhoto,
      'national_id_number' => l.reqNationalIdNumber,
      'national_id_image' => l.reqNationalIdImage,
      'licence_number' => l.reqLicenceNumber,
      'licence_image' => l.reqLicenceImage,
      'vehicle' => l.reqVehicle,
      _ => key,
    };

String failureReasonLabel(AppLocalizations l, String reason) => switch (reason) {
      'customer_unreachable' => l.failCustomerUnreachable,
      'vehicle_breakdown' => l.failVehicleBreakdown,
      'accident' => l.failAccident,
      'item_damaged' => l.failItemDamaged,
      'wrong_address' => l.failWrongAddress,
      _ => l.failOther,
    };

String driverStatusLabel(AppLocalizations l, DriverProfile p) {
  if (p.isDraft) return l.statusDraft;
  return switch (p.status) {
    'approved' => l.statusApproved,
    'rejected' => l.statusRejected,
    'suspended' => l.statusSuspended,
    _ => l.statusPending,
  };
}
