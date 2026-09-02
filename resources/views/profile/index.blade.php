@extends('layouts.app')

@section('title', 'Profile Settings | NCMB ICT System')
@section('page-title', 'Profile Settings')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .profile-container {
            width: 100%;
            margin-top: -10px;
        }

        /* 2-Column Grid */
        .profile-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
            align-items: start;
        }

        .polish-card {
            background: white;
            border-radius: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Left Column: Avatar Profile Card */
        .profile-summary-card {
            text-align: center;
            padding: 40px 20px 30px;
        }
        
        .avatar-initials {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
            color: white;
            font-size: 40px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(0, 56, 168, 0.2);
            border: 4px solid white;
        }

        .summary-name {
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 5px;
        }

        .summary-email {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 20px;
        }

        .summary-role-badge {
            display: inline-block;
            background: #eff6ff;
            color: #1e40af;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #dbeafe;
        }

        .summary-dept {
            margin-top: 15px;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }

        /* Right Column: Settings Form */
        .card-header-accent {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .profile-header-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .profile-header-desc { margin: 2px 0 0; font-size: 12px; color: #64748b; }

        .card-body-content {
            padding: 30px;
        }

        .form-grid-simple {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .label-simple {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        /* Input wrapper for icon */
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 13px;
        }

        .input-simple {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-simple:focus {
            outline: none;
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
        }

        .input-simple:disabled {
            background: #f8fafc;
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 13px;
            padding: 0;
            outline: none;
        }
        
        .password-toggle:hover {
            color: #0038A8;
        }

        .section-header-simple {
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            margin: 35px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .btn-update-gov {
            background: #0038A8;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-update-gov:hover {
            background: #002366;
        }

        .field-error { color: #ef4444; font-size: 11px; margin-top: 5px; display: block; }
        .input-capitalize { text-transform: capitalize; }
        
        .form-actions { margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px; padding-top: 25px; border-top: 1px solid #f1f5f9; }
        .btn-cancel { padding: 12px 25px; text-decoration: none; color: #64748b; font-weight: 700; font-size: 14px; }
        
        @media (max-width: 992px) {
            .profile-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 767px) {
            .form-grid-simple { grid-template-columns: 1fr !important; gap: 15px !important; }
            .form-actions { flex-direction: column !important; gap: 10px !important; }
            .btn-cancel, .btn-update-gov { width: 100% !important; text-align: center !important; }
            .btn-cancel { border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; }
        }
    </style>
@endsection

@section('content')
<div class="profile-container">

    @if (session('success'))
        <div id="globalAlertSuccess" style="display:none">{{ session('success') }}</div>
    @endif

    <div class="profile-layout">
        
        <!-- LEFT COLUMN: AVATAR CARD -->
        <div class="polish-card profile-summary-card">
            <h2 class="summary-name" style="margin-top: 10px;">{{ Auth::user()->full_name }}</h2>
            <p class="summary-email">{{ Auth::user()->email }}</p>
            <div class="summary-role-badge">
                {{ str_replace('_', ' ', Auth::user()->role) }}
            </div>
            @if(Auth::user()->office || Auth::user()->department)
                <div class="summary-dept">
                    {{ Auth::user()->office ?: 'Central Office' }} 
                    @if(Auth::user()->department) <br> <span style="opacity: 0.8; font-size: 12px;">{{ Auth::user()->department }}</span> @endif
                </div>
            @endif
        </div>

        <!-- RIGHT COLUMN: SETTINGS FORM -->
        <div class="polish-card">
            <div class="card-header-accent">
                <h3 class="profile-header-title">Account Configuration</h3>
                <p class="profile-header-desc">Manage your personal details and security credentials.</p>
            </div>

            <form action="{{ route('profile.update') }}" method="POST" class="card-body-content">
                @csrf
                @method('PUT')

                <div class="form-grid-simple">
                    <div class="form-group">
                        <label class="label-simple">Full Name</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="input-simple" required>
                        </div>
                        @error('full_name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="label-simple">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input-simple" required>
                        </div>
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="label-simple">Position / Designation</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-id-badge input-icon"></i>
                            <input type="text" name="position" value="{{ old('position', $user->position) }}" class="input-simple">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="label-simple">Division / Office</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-building input-icon"></i>
                            <input type="text" value="{{ old('office', $user->office) }}" class="input-simple" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="label-simple">Department</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-network-wired input-icon"></i>
                            <input type="text" value="{{ old('department', $user->department) }}" class="input-simple" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="label-simple">System Role</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-shield-halved input-icon"></i>
                            <input type="text" value="{{ str_replace('_', ' ', $user->role) }}" class="input-simple input-capitalize" disabled>
                        </div>
                    </div>
                </div>

                <div class="section-header-simple">
                    Authentication Security
                </div>

                <div class="form-grid-simple">
                    <div class="form-group">
                        <label class="label-simple">New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key input-icon"></i>
                            <input type="password" name="password" id="newPassword" placeholder="Leave blank to keep current" class="input-simple">
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        @error('password') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="label-simple">Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-check-double input-icon"></i>
                            <input type="password" name="password_confirmation" id="confirmPassword" placeholder="Verify new password" class="input-simple">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route(Auth::user()->dashboardRouteName()) }}" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-update-gov">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
    // Password visibility toggle
    function togglePassword(inputId, btnElement) {
        const input = document.getElementById(inputId);
        const icon = btnElement.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Hide the green success bar and show SweetAlert instead
    var alertBox = document.getElementById('globalAlertSuccess');
    if (alertBox) {
        var msg = alertBox.textContent.trim();
        alertBox.style.display = 'none';
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: msg,
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'OK'
        });
    }
</script>
@endsection
