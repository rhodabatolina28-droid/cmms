@php
    $pageTitle = 'Edit PM Schedule';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .premium-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-title { font-size: 20px; font-weight: 800; color: #1e293b; margin: 0 0 6px; }
    .form-subtitle { font-size: 12px; color: #64748b; margin: 0 0 28px; }
    .form-section { margin-bottom: 28px; }
    .form-section:last-of-type { margin-bottom: 0; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 0; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #1e293b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
    .form-group .hint { font-size: 11px; color: #64748b; margin-top: 8px; line-height: 1.5; }
    .form-group .required { color: #ef4444; margin-left: 2px; }
    .input-focus { width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:13px; transition:border-color 0.2s; outline:none; background: white; }
    .input-focus:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,0.1); }
    .select-full { width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:13px; outline:none; background: white; cursor: pointer; }
    .select-full:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,0.1); }
    .page-container { max-width:1200px; margin:0 auto; padding:0 20px; }
    .error-text { color:#dc2626; font-size:12px; margin:6px 0 0; font-weight:600; }
    .form-divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
    .checkbox-wrapper { display: flex; align-items: center; }
    .checkbox-label { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.2s; }
    .checkbox-label:hover { background: #f1f5f9; border-color: #0038A8; }
    .checkbox-custom { width: 18px; height: 18px; accent-color: #0038A8; cursor: pointer; flex-shrink: 0; }
    .checkbox-label span { font-size: 14px; font-weight: 600; color: #1e293b; }
    .form-actions { display:flex; gap:12px; justify-content:flex-end; margin-top:32px; padding-top:24px; border-top:1px solid #e2e8f0; }
    .btn-submit { padding:12px 28px; background:#0038A8; color:white; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s; }
    .btn-submit:hover { background:#002d8c; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,56,168,0.2); }
    .btn-cancel { padding:12px 28px; background:#f1f5f9; color:#475569; border:none; border-radius:10px; font-size:14px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s; }
    .btn-cancel:hover { background:#e2e8f0; transform:translateY(-1px); }
    @media (max-width: 767px) {
        .premium-card { padding: 20px 16px !important; }
        .form-title { font-size: 18px !important; }
        .form-subtitle { font-size: 12px !important; margin-bottom: 20px !important; }
        .form-row { grid-template-columns: 1fr !important; gap: 0 !important; }
        .form-section { margin-bottom: 20px !important; }
        .form-group label { font-size: 11px !important; }
        .form-group .hint { font-size: 10px !important; }
        .input-focus, .select-full { min-height: 48px !important; font-size: 15px !important; padding: 12px 14px !important; }
        .form-actions { flex-direction: column !important; gap: 10px !important; margin-top: 24px !important; padding-top: 20px !important; }
        .btn-submit, .btn-cancel { width: 100% !important; justify-content: center !important; min-height: 48px !important; font-size: 14px !important; }
        .checkbox-label { padding: 10px 14px !important; }
    }
    @media (max-width: 480px) {
        .premium-card { padding: 16px 14px !important; }
        .form-title { font-size: 16px !important; }
        .form-subtitle { font-size: 11px !important; }
    }
</style>
@endsection

@section('content')
<div class="page-container">
    <div class="premium-card">
        <h1 class="form-title">Edit PM Schedule</h1>
        <p class="form-subtitle">Update schedule configuration and settings</p>

        <form method="POST" action="{{ route('pm-schedules.update', $pmSchedule->id) }}" id="scheduleForm">
            @csrf
            @method('PUT')

            <div class="form-section">
                <div class="form-group">
                    <label>Schedule Name <span class="required">*</span></label>
                    <input type="text" name="schedule_name" value="{{ old('schedule_name', $pmSchedule->schedule_name) }}" required
                        class="input-focus"
                        placeholder="e.g. RID Desktops Monthly">
                    <div class="hint">A descriptive name to identify this maintenance schedule</div>
                    @error('schedule_name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <hr class="form-divider">

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label>Target Division</label>
                        <select name="division_filter" class="select-full">
                            <option value="">All Divisions (cycles through all)</option>
                            @foreach(['RID'=>'Research & Information Division','AD'=>'Administrative Division','FMD'=>'Financial & Management Division','COA'=>'Commission on Audit','CMD'=>'Conciliation & Mediation Division','VAD'=>'Voluntary Arbitration Division','WRED'=>'Workplace Relations Enhancement Division','OED'=>'Office of the Executive Director'] as $val=>$label)
                                <option value="{{ $val }}" {{ old('division_filter', $pmSchedule->division_filter) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="hint">Leave as "All Divisions" to cycle through all divisions automatically.</div>
                    </div>

                    <div class="form-group">
                        <label>Frequency <span class="required">*</span></label>
                        <select name="frequency" required class="select-full">
                            @foreach(['Monthly','Quarterly','Semi-annual','Annual'] as $freq)
                                <option value="{{ $freq }}" {{ old('frequency', $pmSchedule->frequency) === $freq ? 'selected' : '' }}>{{ $freq }}</option>
                            @endforeach
                        </select>
                        <div class="hint">How often each user receives a preventive maintenance ticket.</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $pmSchedule->is_active) ? 'checked' : '' }} class="checkbox-custom">
                        <span>Active Schedule</span>
                    </label>
                    <div class="hint" style="margin-top:8px;margin-left:0;">Inactive schedules will not generate PM requests</div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('pm-schedules.show', $pmSchedule->id) }}" class="btn-cancel">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i> Update Schedule
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

