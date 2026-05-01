<!DOCTYPE html>
{{--
    Pay slip PDF body. Rendered by DomPDF for `Download PDF` and as an
    email attachment. Avoid web fonts / CSS variables — DomPDF doesn't
    cope with them; stick to inline styles + system fonts.

    Uses the frozen snapshot on the payslip (employee_snapshot_json,
    line_items_json) so the PDF reflects exactly what was locked at
    payroll run time, not whatever the live admin record says now.
--}}
<html>
<head>
    <meta charset="utf-8">
    <title>Pay Slip · {{ optional($run->period_from)->format('M Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1A1A1A; margin: 0; padding: 0; }
        .ps { padding: 22px 28px; }
        .ps-head { border-bottom: 2px solid #1A1A1A; padding-bottom: 10px; margin-bottom: 14px; }
        .ps-head .co { font-size: 18px; font-weight: 800; }
        .ps-head .sub { font-size: 11px; color: #6A6A70; margin-top: 2px; }
        .ps-title { font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin: 14px 0 4px; }
        .ps-period { font-size: 11px; color: #6A6A70; margin-bottom: 14px; }

        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 6px; font-size: 11px; vertical-align: top; }
        .meta .label { color: #6A6A70; font-weight: 600; width: 110px; }

        .lines { margin-top: 14px; }
        .lines th, .lines td {
            padding: 6px 8px; border-bottom: 1px solid #E5E7EB;
            font-size: 11px; text-align: left;
        }
        .lines th {
            background: #F4F6F8; color: #6A6A70;
            font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
            font-size: 9px;
        }
        .lines td.amt, .lines th.amt { text-align: right; font-variant-numeric: tabular-nums; }

        .totals { margin-top: 10px; }
        .totals td { padding: 5px 8px; font-size: 12px; }
        .totals td.label { color: #6A6A70; text-align: right; }
        .totals td.amt { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
        .totals tr.net td { font-size: 14px; font-weight: 800; border-top: 2px solid #1A1A1A; padding-top: 10px; }

        .pay-block {
            margin-top: 18px; padding: 10px 12px;
            background: #F4F6F8; border-radius: 6px;
            font-size: 11px;
        }
        .pay-block .row { margin-bottom: 3px; }
        .pay-block .label { color: #6A6A70; display: inline-block; width: 110px; }

        .footer {
            margin-top: 24px; padding-top: 10px; border-top: 1px solid #E5E7EB;
            font-size: 9px; color: #9095A0; text-align: center;
        }

        .pill {
            display: inline-block; padding: 1px 6px; border-radius: 999px;
            font-size: 9px; font-weight: 800; letter-spacing: 1px;
        }
        .pill.paid    { background: #ECFFEF; color: #1E8E3E; }
        .pill.unpaid  { background: #FFF4E5; color: #B45A0A; }
    </style>
</head>
<body>
    <div class="ps">

        <div class="ps-head">
            <div class="co">{{ $companyName ?: 'Lahab' }}</div>
            <div class="sub">{{ $branchName ? $branchName . ' · ' : '' }}{{ translate('Pay slip generated') }} {{ now()->format('d M Y, H:i') }}</div>
        </div>

        @php
            $snap = $payslip->employee_snapshot_json ?? [];
            $lines = $payslip->line_items_json ?? [];
            $allowances = collect($lines)->where('type', 'allowance')->where('amount', '>', 0)->values();
            $deductions = collect($lines)->where('type', 'deduction')->where('amount', '>', 0)->values();
        @endphp

        <div class="ps-title">{{ translate('Pay Slip') }}
            @if($payslip->paid_at)
                <span class="pill paid">PAID</span>
            @else
                <span class="pill unpaid">PENDING</span>
            @endif
        </div>
        <div class="ps-period">
            {{ translate('Period') }}: {{ optional($run->period_from)->format('d M') }} – {{ optional($run->period_to)->format('d M Y') }}
            · {{ translate('Run') }} #{{ $run->id }}
        </div>

        <table class="meta">
            <tr>
                <td class="label">{{ translate('Employee') }}</td>
                <td>
                    <strong>{{ trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) }}</strong>
                    @if(!empty($snap['employee_code'])) · {{ $snap['employee_code'] }}@endif
                </td>
                <td class="label">{{ translate('Designation') }}</td>
                <td>{{ $snap['designation'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ translate('Department') }}</td>
                <td>{{ $snap['department_name'] ?? '—' }}</td>
                <td class="label">{{ translate('Branch') }}</td>
                <td>{{ $branchName ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ translate('Days clocked') }}</td>
                <td>{{ $payslip->days_clocked }} / {{ $payslip->calendar_days }}</td>
                <td class="label">{{ translate('Hours') }}</td>
                <td>{{ number_format($payslip->attendance_minutes / 60, 1) }} h</td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width:55%;">{{ translate('Earnings') }}</th>
                    <th class="amt">{{ translate('Amount (Tk)') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allowances as $line)
                    <tr>
                        <td>{{ $line['name'] ?? '—' }}</td>
                        <td class="amt">{{ number_format((float) $line['amount'], 2) }}</td>
                    </tr>
                @endforeach
                @if($payslip->tip_share > 0)
                    <tr>
                        <td>{{ translate('Tip / service charge share') }}</td>
                        <td class="amt">{{ number_format((float) $payslip->tip_share, 2) }}</td>
                    </tr>
                @endif
                <tr style="background:#F4F6F8;">
                    <td><strong>{{ translate('Gross earnings') }}</strong></td>
                    <td class="amt"><strong>{{ number_format((float) $payslip->gross, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        @if($deductions->count() > 0 || $payslip->advance_recovery > 0)
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:55%;">{{ translate('Deductions') }}</th>
                    <th class="amt">{{ translate('Amount (Tk)') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deductions as $line)
                    <tr>
                        <td>{{ $line['name'] ?? '—' }}</td>
                        <td class="amt">− {{ number_format((float) $line['amount'], 2) }}</td>
                    </tr>
                @endforeach
                @if($payslip->advance_recovery > 0)
                    <tr>
                        <td>{{ translate('Advance recovery') }}</td>
                        <td class="amt">− {{ number_format((float) $payslip->advance_recovery, 2) }}</td>
                    </tr>
                @endif
                <tr style="background:#F4F6F8;">
                    <td><strong>{{ translate('Total deductions') }}</strong></td>
                    <td class="amt"><strong>− {{ number_format((float) $payslip->prorated_deduction + (float) $payslip->advance_recovery, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
        @endif

        <table class="totals">
            <tr class="net">
                <td class="label">{{ translate('NET PAYABLE') }}</td>
                <td class="amt">Tk {{ number_format((float) $payslip->net, 2) }}</td>
            </tr>
        </table>

        @if($payslip->paid_at)
        <div class="pay-block">
            <div class="row"><span class="label">{{ translate('Paid on') }}</span> {{ $payslip->paid_at->format('d M Y, H:i') }}</div>
            <div class="row"><span class="label">{{ translate('Method') }}</span> {{ strtoupper($payslip->paid_method ?? 'cash') }}</div>
            @if($payslip->paid_reference)
                <div class="row"><span class="label">{{ translate('Reference') }}</span> {{ $payslip->paid_reference }}</div>
            @endif
        </div>
        @endif

        <div class="footer">
            {{ translate('This is a computer-generated pay slip and does not require a signature.') }}
            · {{ translate('Generated by Lahab HRM') }}
        </div>
    </div>
</body>
</html>
