<?php

namespace EcoModular\DeliverySlots\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use EcoModular\DeliverySlots\DeliverySlotsSettings;
use EcoModular\DeliverySlots\Models\DeliveryBlackoutDate;
use EcoModular\DeliverySlots\Models\DeliverySlot;
use EcoModular\DeliverySlots\Models\OrderDeliverySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliverySlotsAdminController extends Controller
{
    public function index(Request $request, StoreSettings $storeSettings): Response
    {
        $tz = $storeSettings->timezone() ?: config('app.timezone', 'UTC');
        $today = now($tz)->toDateString();

        $dateFrom = $request->input('date_from', $today);
        $dateTo = $request->input('date_to', Carbon::parse($dateFrom)->addDays(6)->toDateString());
        $slotId = $request->input('slot_id');

        $query = OrderDeliverySchedule::query()
            ->with(['order.customer', 'slot'])
            ->whereBetween('delivery_date', [$dateFrom, $dateTo]);

        if (! empty($slotId)) {
            $query->where('delivery_slot_id', $slotId);
        }

        $schedules = $query->orderBy('delivery_date')
            ->orderBy('delivery_slot_id')
            ->get()
            ->map(function (OrderDeliverySchedule $item) {
                $order = $item->order;
                $customerName = $order?->customer
                    ? trim("{$order->customer->first_name} {$order->customer->last_name}")
                    : 'Guest Customer';

                $formattedTotal = $order ? Money::of($order->grand_total, $order->currency)->format() : '-';

                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_number' => $order?->number ?? "#{$item->order_id}",
                    'delivery_date' => $item->delivery_date->toDateString(),
                    'formatted_date' => $item->delivery_date->format('M j, Y (D)'),
                    'slot_id' => $item->delivery_slot_id,
                    'slot_name' => $item->slot_name,
                    'slot_window' => $item->slot ? "{$item->slot->start_time} - {$item->slot->end_time}" : null,
                    'delivery_instructions' => $item->delivery_instructions,
                    'customer_name' => $customerName,
                    'customer_email' => $order?->email,
                    'customer_phone' => $order?->phone,
                    'order_status' => $order?->status?->value ?? 'pending',
                    'order_total' => $formattedTotal,
                    'order_url' => $order ? route('admin.orders.show', $order) : null,
                ];
            });

        $totalFuture = OrderDeliverySchedule::query()
            ->where('delivery_date', '>=', $today)
            ->count();

        $todayCount = OrderDeliverySchedule::query()
            ->where('delivery_date', $today)
            ->count();

        $activeSlotsCount = DeliverySlot::query()->where('is_active', true)->count();
        $allSlots = DeliverySlot::query()->orderBy('order')->orderBy('start_time')->get();

        return Inertia::render('module/modrixcommerce/delivery-slots/admin/index', [
            'schedules' => $schedules,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'slot_id' => $slotId ? (int) $slotId : null,
            ],
            'stats' => [
                'total_scheduled' => $totalFuture,
                'today_deliveries' => $todayCount,
                'active_slots' => $activeSlotsCount,
            ],
            'slots' => $allSlots->map(fn (DeliverySlot $slot) => [
                'id' => $slot->id,
                'name' => $slot->name,
                'time_range' => "{$slot->start_time} - {$slot->end_time}",
            ]),
            'urls' => [
                'index' => route('admin.modrixcommerce.delivery-slots.index'),
                'slots' => route('admin.modrixcommerce.delivery-slots.slots'),
                'settings' => route('admin.modrixcommerce.delivery-slots.settings'),
            ],
        ]);
    }

    public function slots(): Response
    {
        $slots = DeliverySlot::query()
            ->withCount('schedules')
            ->orderBy('order')
            ->orderBy('start_time')
            ->get()
            ->map(fn (DeliverySlot $slot) => [
                'id' => $slot->id,
                'name' => $slot->name,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'cutoff_hours' => $slot->cutoff_hours,
                'max_orders' => $slot->max_orders,
                'fee' => (float) $slot->fee,
                'is_active' => $slot->is_active,
                'days_of_week' => $slot->days_of_week ?? [0, 1, 2, 3, 4, 5, 6],
                'order' => $slot->order,
                'total_bookings' => $slot->schedules_count,
            ]);

        return Inertia::render('module/modrixcommerce/delivery-slots/admin/slots', [
            'slots' => $slots,
            'urls' => [
                'index' => route('admin.modrixcommerce.delivery-slots.index'),
                'slots' => route('admin.modrixcommerce.delivery-slots.slots'),
                'store' => route('admin.modrixcommerce.delivery-slots.slots.store'),
                'settings' => route('admin.modrixcommerce.delivery-slots.settings'),
            ],
        ]);
    }

    public function storeSlot(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'cutoff_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'max_orders' => ['required', 'integer', 'min:0', 'max:10000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        $data['fee'] = $data['fee'] ?? 0;
        $data['days_of_week'] = $data['days_of_week'] ?? [0, 1, 2, 3, 4, 5, 6];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['order'] = $data['order'] ?? 0;

        $slot = DeliverySlot::create($data);

        $audit->record('delivery_slot.created', null, ['id' => $slot->id, 'name' => $slot->name]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Delivery slot '{$slot->name}' created successfully.",
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.slots');
    }

    public function updateSlot(Request $request, DeliverySlot $slot, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'cutoff_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'max_orders' => ['required', 'integer', 'min:0', 'max:10000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        $data['fee'] = $data['fee'] ?? 0;
        $data['days_of_week'] = $data['days_of_week'] ?? [0, 1, 2, 3, 4, 5, 6];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['order'] = $data['order'] ?? 0;

        $slot->update($data);

        $audit->record('delivery_slot.updated', null, ['id' => $slot->id, 'name' => $slot->name]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Delivery slot '{$slot->name}' updated successfully.",
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.slots');
    }

    public function deleteSlot(DeliverySlot $slot, AuditLogger $audit): RedirectResponse
    {
        $name = $slot->name;
        $slot->delete();

        $audit->record('delivery_slot.deleted', null, ['id' => $slot->id, 'name' => $name]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Delivery slot '{$name}' removed successfully.",
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.slots');
    }

    public function toggleSlot(DeliverySlot $slot): RedirectResponse
    {
        $slot->update(['is_active' => ! $slot->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Slot '{$slot->name}' is now ".($slot->is_active ? 'active' : 'disabled').'.',
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.slots');
    }

    public function settings(DeliverySlotsSettings $settings): Response
    {
        $blackoutDates = DeliveryBlackoutDate::query()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn (DeliveryBlackoutDate $item) => [
                'id' => $item->id,
                'date' => $item->date->toDateString(),
                'formatted' => $item->date->format('M j, Y (l)'),
                'reason' => $item->reason,
            ]);

        return Inertia::render('module/modrixcommerce/delivery-slots/admin/settings', [
            'settings' => $settings->toArray(),
            'blackoutDates' => $blackoutDates,
            'urls' => [
                'index' => route('admin.modrixcommerce.delivery-slots.index'),
                'slots' => route('admin.modrixcommerce.delivery-slots.slots'),
                'settings' => route('admin.modrixcommerce.delivery-slots.settings'),
                'updateSettings' => route('admin.modrixcommerce.delivery-slots.settings.update'),
                'storeBlackout' => route('admin.modrixcommerce.delivery-slots.blackouts.store'),
            ],
        ]);
    }

    public function updateSettings(
        Request $request,
        DeliverySlotsSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'is_required' => ['boolean'],
            'max_advance_days' => ['required', 'integer', 'between:1,30'],
            'same_day_cutoff' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'heading' => ['required', 'string', 'max:120'],
            'method_rules' => ['nullable', 'array'],
            'method_rules.*.enabled' => ['boolean'],
            'method_rules.*.max_days' => ['required_with:method_rules', 'integer', 'between:1,30'],
            'method_rules.*.show_time_slots' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['is_required'] = $request->boolean('is_required');

        $settings->save($data);

        $audit->record('delivery_slots.settings_updated', null, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Delivery slot settings saved successfully.',
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.settings');
    }

    public function storeBlackout(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('delivery_blackout_dates', 'date')],
            'reason' => ['nullable', 'string', 'max:150'],
        ]);

        $blackout = DeliveryBlackoutDate::create($data);

        $audit->record('delivery_blackout.created', null, ['date' => $blackout->date->toDateString()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Blackout date for {$blackout->date->toDateString()} added.",
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.settings');
    }

    public function deleteBlackout(DeliveryBlackoutDate $blackout, AuditLogger $audit): RedirectResponse
    {
        $dateStr = $blackout->date->toDateString();
        $blackout->delete();

        $audit->record('delivery_blackout.deleted', null, ['date' => $dateStr]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Blackout date for {$dateStr} removed.",
        ]);

        return redirect()->route('admin.modrixcommerce.delivery-slots.settings');
    }
}
