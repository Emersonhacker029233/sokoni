import 'package:flutter_test/flutter_test.dart';
import 'package:sokoni_driver/data/models/driver_job.dart';
import 'package:sokoni_driver/features/job/job_steps.dart';

import 'fixtures.dart';

DriverJob job(String status, {List<String> next = const [], int calls = 0, String? unreachableAt}) =>
    DriverJob.fromJson(jobJson(status: status, next: next, callAttempts: calls, unreachableAvailableAt: unreachableAt));

void main() {
  group('nextStep', () {
    test('walks the five driver steps in order', () {
      expect(nextStep(job('driver_assigned', next: ['arrived_at_shop'])), JobStep.arriveAtShop);
      expect(nextStep(job('arrived_at_shop', next: ['picked_up'])), JobStep.confirmPickup);
      expect(nextStep(job('picked_up', next: ['on_the_way', 'arrived', 'failed'])), JobStep.startTrip);
      expect(nextStep(job('on_the_way', next: ['arrived', 'failed'])), JobStep.arriveAtCustomer);
      expect(nextStep(job('arrived', next: ['delivered', 'failed'])), JobStep.completeDelivery);
    });

    test('defers to the server when it withholds a step', () {
      // e.g. PIN locked: the server stops offering "delivered".
      expect(nextStep(job('arrived', next: ['failed'])), JobStep.none);
    });

    test('finished jobs have no next step', () {
      for (final s in ['delivered', 'cancelled', 'failed']) {
        expect(nextStep(job(s)), JobStep.none);
      }
    });

    test('only pickup and delivery need a live secret', () {
      expect(JobStep.confirmPickup.needsSecret, isTrue);
      expect(JobStep.completeDelivery.needsSecret, isTrue);
      expect(JobStep.arriveAtShop.needsSecret, isFalse);
      expect(JobStep.startTrip.needsSecret, isFalse);
      expect(JobStep.arriveAtCustomer.needsSecret, isFalse);
    });
  });

  test('target switches from shop to customer at pickup', () {
    expect(targetOf(job('driver_assigned')), JobTarget.shop);
    expect(targetOf(job('arrived_at_shop')), JobTarget.shop);
    expect(targetOf(job('picked_up')), JobTarget.customer);
    expect(targetOf(job('arrived')), JobTarget.customer);
    expect(targetOf(job('delivered')), JobTarget.none);
  });

  test('problems can be reported only while carrying the goods', () {
    expect(canReportProblem(job('arrived_at_shop')), isFalse);
    expect(canReportProblem(job('picked_up')), isTrue);
    expect(canReportProblem(job('arrived')), isTrue);
  });

  group('unreachableAllowed mirrors the 10 minute / 2 call rule', () {
    final now = DateTime.utc(2026, 11, 1, 9);

    test('too early', () {
      expect(unreachableAllowed(job('arrived', calls: 3, unreachableAt: '2026-11-01T09:05:00Z'), now), isFalse);
    });

    test('not enough calls', () {
      expect(unreachableAllowed(job('arrived', calls: 1, unreachableAt: '2026-11-01T08:55:00Z'), now), isFalse);
    });

    test('waited and called twice', () {
      expect(unreachableAllowed(job('arrived', calls: 2, unreachableAt: '2026-11-01T08:55:00Z'), now), isTrue);
    });

    test('never before arriving', () {
      expect(unreachableAllowed(job('on_the_way', calls: 5, unreachableAt: '2026-11-01T08:00:00Z'), now), isFalse);
    });
  });
}
