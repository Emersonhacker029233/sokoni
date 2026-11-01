import '../../data/models/driver_job.dart';

/// The one primary thing a driver does next on a job.
enum JobStep {
  arriveAtShop('arrived_at_shop'),
  confirmPickup('picked_up'),
  startTrip('on_the_way'),
  arriveAtCustomer('arrived'),
  completeDelivery('delivered'),
  none(null);

  const JobStep(this.status);

  /// The status this step posts to the API.
  final String? status;

  /// Needs the seller's pickup code or the customer's PIN checked live.
  bool get needsSecret => this == confirmPickup || this == completeDelivery;
}

/// Where the driver is heading right now.
enum JobTarget { shop, customer, none }

JobStep nextStep(DriverJob job) {
  final allowed = job.actions.next;
  final step = switch (job.status) {
    'driver_assigned' => JobStep.arriveAtShop,
    'arrived_at_shop' => JobStep.confirmPickup,
    'picked_up' => JobStep.startTrip,
    'on_the_way' => JobStep.arriveAtCustomer,
    'arrived' => JobStep.completeDelivery,
    _ => JobStep.none,
  };
  // Trust the server's list when it has one (e.g. PIN locked => no delivered).
  if (step != JobStep.none && allowed.isNotEmpty && !allowed.contains(step.status)) return JobStep.none;
  return step;
}

JobTarget targetOf(DriverJob job) => switch (job.status) {
      'driver_assigned' || 'arrived_at_shop' => JobTarget.shop,
      'picked_up' || 'on_the_way' || 'arrived' => JobTarget.customer,
      _ => JobTarget.none,
    };

/// "Report a problem" exists once the goods are with the driver;
/// before that the driver cancels instead.
bool canReportProblem(DriverJob job) => const ['picked_up', 'on_the_way', 'arrived'].contains(job.status);

/// Whether the "customer unreachable" reason can be chosen yet. The server
/// re-checks; this only greys the option out with an explanation.
bool unreachableAllowed(DriverJob job, DateTime now, {int requiredCalls = 2}) {
  if (job.status != 'arrived') return false;
  final from = job.unreachableAvailableAt;
  return from != null && !now.isBefore(from) && job.callAttempts >= requiredCalls;
}
