<?php

namespace App\Mail;

use App\Model\BusinessSetting;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * HRM Phase 7b — Pay slip email with PDF attachment.
 *
 * Generates the pay slip PDF inline (no on-disk caching) so the email
 * always reflects the latest state of the snapshot. The body view is
 * a small, plain HTML summary; the PDF holds the formal record.
 *
 * Usage:
 *   Mail::to($payslip->employee->email)->send(new PaySlipEmail($payslip));
 */
class PaySlipEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Payslip $payslip;

    public function __construct(Payslip $payslip)
    {
        $this->payslip = $payslip;
    }

    public function build()
    {
        $payslip = $this->payslip->load(['run', 'branch']);
        $run     = $payslip->run;

        $companyName = optional(BusinessSetting::where('key', 'restaurant_name')->first())->value ?? 'Lahab';
        $branchName  = $payslip->branch?->name ?? '';

        // Render PDF body and attach as raw bytes.
        $pdf = Pdf::loadView('admin-views.payroll-run.payslip-pdf', [
            'payslip'     => $payslip,
            'run'         => $run,
            'companyName' => $companyName,
            'branchName'  => $branchName,
        ])->setPaper('a4', 'portrait');

        $period = optional($run?->period_from)->format('M_Y') ?: 'period';
        $snap   = $payslip->employee_snapshot_json ?? [];
        $name   = trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) ?: 'employee';
        $filename = 'PaySlip_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . '_' . $period . '.pdf';

        return $this->subject('Pay slip · ' . ($run?->period_from?->format('M Y') ?? 'period'))
            ->view('email-templates.payslip-email', [
                'payslip'     => $payslip,
                'run'         => $run,
                'companyName' => $companyName,
                'branchName'  => $branchName,
                'employeeName' => $name,
            ])
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
