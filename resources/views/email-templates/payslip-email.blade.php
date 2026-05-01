<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pay slip</title>
</head>
<body style="margin:0; padding:0; background:#F4F6F8; font-family:Arial,sans-serif; color:#1A1A1A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F8; padding:30px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background:#1A1A1A; padding:18px 24px; color:#fff;">
                            <div style="font-size:18px; font-weight:700;">{{ $companyName }}</div>
                            <div style="font-size:12px; color:#9095A0; margin-top:2px;">
                                {{ translate('Pay slip') }} · {{ optional($run?->period_from)->format('M Y') }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;">
                                {{ translate('Hi') }} <strong>{{ $employeeName }}</strong>,
                            </p>
                            <p style="margin:0 0 16px; line-height:1.6; font-size:14px;">
                                {{ translate('Your pay slip for') }}
                                <strong>{{ optional($run?->period_from)->format('d M') }} – {{ optional($run?->period_to)->format('d M Y') }}</strong>
                                {{ translate('is attached as a PDF.') }}
                                @if($payslip->paid_at)
                                    {{ translate('It has been paid on') }} {{ $payslip->paid_at->format('d M Y') }}
                                    @if($payslip->paid_method) {{ translate('via') }} <strong>{{ strtoupper($payslip->paid_method) }}</strong>@endif
                                    @if($payslip->paid_reference) ({{ translate('reference') }} {{ $payslip->paid_reference }})@endif.
                                @endif
                            </p>

                            <table width="100%" cellpadding="6" cellspacing="0" style="font-size:13px; border:1px solid #E5E7EB; border-radius:6px; margin:12px 0;">
                                <tr>
                                    <td style="color:#6A6A70;">{{ translate('Days clocked') }}</td>
                                    <td style="text-align:right;">{{ $payslip->days_clocked }} / {{ $payslip->calendar_days }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#6A6A70;">{{ translate('Gross earnings') }}</td>
                                    <td style="text-align:right;">Tk {{ number_format((float) $payslip->gross, 2) }}</td>
                                </tr>
                                @if($payslip->prorated_deduction > 0 || $payslip->advance_recovery > 0)
                                <tr>
                                    <td style="color:#6A6A70;">{{ translate('Total deductions') }}</td>
                                    <td style="text-align:right; color:#C82626;">
                                        − Tk {{ number_format((float) $payslip->prorated_deduction + (float) $payslip->advance_recovery, 2) }}
                                    </td>
                                </tr>
                                @endif
                                <tr style="background:#F4F6F8; font-weight:700; font-size:15px;">
                                    <td>{{ translate('NET PAYABLE') }}</td>
                                    <td style="text-align:right;">Tk {{ number_format((float) $payslip->net, 2) }}</td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0; font-size:12px; color:#6A6A70;">
                                {{ translate('Open the attached PDF for the full breakdown. If anything looks off, contact HR.') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px; background:#FAFBFC; font-size:11px; color:#9095A0; text-align:center;">
                            {{ translate('This is a computer-generated email from') }} {{ $companyName }} {{ translate('HRM') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
