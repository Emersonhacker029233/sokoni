<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\DriverProfile;
use App\Services\Delivery\DriverReviewService;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Everything a reviewer needs on one page: who, identity numbers, vehicle,
 * the document images side by side, and any delivery in progress.
 */
class DriverInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Driver')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('Name'),
                        TextEntry::make('user.phone')
                            ->label('Phone')
                            ->copyable()
                            ->url(fn (DriverProfile $record) => $record->user?->phone ? 'tel:'.$record->user->phone : null),
                        TextEntry::make('review_state')
                            ->label('Status')
                            ->badge()
                            ->state(fn (DriverProfile $record) => DriversTable::stateLabel($record))
                            ->color(fn (DriverProfile $record) => DriverReviewService::needsReview($record) ? 'warning' : match ($record->status) {
                                'approved' => 'success',
                                'rejected', 'suspended' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('submitted_at')->label('Submitted')->dateTime()->placeholder('Not submitted yet'),
                        TextEntry::make('approved_at')
                            ->label('Approved')
                            ->dateTime()
                            ->placeholder('-')
                            ->helperText(fn (DriverProfile $record) => $record->approved_by
                                ? 'by '.(\App\Models\User::query()->whereKey($record->approved_by)->value('name') ?? '#'.$record->approved_by)
                                : null),
                        TextEntry::make('missing')
                            ->label('Still missing')
                            ->state(fn (DriverProfile $record) => $record->missingRequirements() === []
                                ? 'Nothing'
                                : implode(', ', str_replace('_', ' ', $record->missingRequirements())))
                            ->color(fn (DriverProfile $record) => $record->missingRequirements() === [] ? 'success' : 'danger'),
                        TextEntry::make('rejection_reason')
                            ->label('Rejection reason')
                            ->columnSpanFull()
                            ->visible(fn (DriverProfile $record) => $record->status === 'rejected'),
                        TextEntry::make('suspended_reason')
                            ->label('Suspension reason')
                            ->columnSpanFull()
                            ->visible(fn (DriverProfile $record) => $record->status === 'suspended'),
                        TextEntry::make('flag_reason')
                            ->label('Flagged')
                            ->color('danger')
                            ->columnSpanFull()
                            ->helperText(fn (DriverProfile $record) => $record->flagged_at?->diffForHumans())
                            ->visible(fn (DriverProfile $record) => $record->flagged_at !== null),
                    ]),

                Section::make('Identity')
                    ->description('Check these against the photos below.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('national_id_number')
                            ->label('NIDA number')
                            ->fontFamily('mono')
                            ->size('lg')
                            ->weight('bold')
                            ->copyable()
                            ->placeholder('Not provided'),
                        TextEntry::make('licence_number')->label('Licence number')->copyable()->placeholder('Not provided (allowed for bicycles)'),
                        TextEntry::make('licence_expires_on')
                            ->label('Licence expires')
                            ->date()
                            ->placeholder('-')
                            ->color(fn (DriverProfile $record) => $record->licence_expires_on?->isPast() ? 'danger' : null)
                            ->helperText(fn (DriverProfile $record) => $record->licence_expires_on?->isPast() ? 'Expired' : null),
                        TextEntry::make('terms_accepted_at')
                            ->label('Terms accepted')
                            ->dateTime()
                            ->placeholder('-')
                            ->helperText(fn (DriverProfile $record) => $record->terms_version ? 'Version '.$record->terms_version : null),
                    ]),

                Section::make('Vehicle')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('activeVehicle.type')
                            ->label('Type')
                            ->formatStateUsing(fn (?string $state) => $state ? ucfirst(str_replace('_', ' ', $state)) : null)
                            ->placeholder('No vehicle yet'),
                        TextEntry::make('activeVehicle.plate_number')
                            ->label('Plate')
                            ->fontFamily('mono')
                            ->weight('bold')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('vehicle_detail')
                            ->label('Make and colour')
                            ->state(fn (DriverProfile $record) => collect([$record->activeVehicle?->make, $record->activeVehicle?->colour])->filter()->implode(', ') ?: null)
                            ->placeholder('-'),
                        TextEntry::make('activeVehicle.verified_at')
                            ->label('Verified')
                            ->dateTime()
                            ->placeholder('Not verified')
                            ->color(fn (DriverProfile $record) => $record->activeVehicle?->verified_at ? 'success' : 'warning'),
                    ]),

                Section::make('Documents')
                    ->description('Served only to admins from private storage. Click an image to open it full size.')
                    ->schema([
                        ViewEntry::make('documents')
                            ->hiddenLabel()
                            ->view('filament.drivers.documents'),
                    ]),

                Section::make('Delivery in progress')
                    ->columns(4)
                    ->visible(fn (DriverProfile $record) => $record->activeJob() !== null)
                    ->schema([
                        TextEntry::make('job_id')->label('Job')->state(fn (DriverProfile $record) => '#'.$record->activeJob()?->id),
                        TextEntry::make('job_status')->label('Status')->badge()->state(fn (DriverProfile $record) => $record->activeJob()?->status),
                        TextEntry::make('job_order')->label('Order')->state(fn (DriverProfile $record) => $record->activeJob()?->order?->code),
                        TextEntry::make('job_pin')
                            ->label('Delivery PIN')
                            ->state(fn (DriverProfile $record) => $record->activeJob()?->pin_locked_at ? 'Locked after too many wrong tries' : 'OK')
                            ->color(fn (DriverProfile $record) => $record->activeJob()?->pin_locked_at ? 'danger' : 'success'),
                    ]),

                Section::make('Record')
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('jobs_completed')->label('Deliveries completed'),
                        TextEntry::make('cancellations_count')->label('Cancellations'),
                        TextEntry::make('rating_avg')
                            ->label('Rating')
                            ->state(fn (DriverProfile $record) => $record->rating_count > 0 ? number_format($record->rating_avg, 1).' ('.$record->rating_count.')' : 'No ratings yet'),
                        TextEntry::make('created_at')->label('Signed up')->dateTime(),
                    ]),
            ]);
    }
}
