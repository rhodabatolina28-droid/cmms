@extends('layouts.app')

@section('title', 'Profile Settings | NCMB ICT System')
@section('page-title', 'Profile Settings')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .profile-container {
            width: 100%;
            margin-top: -10px;
            animation: fadeInSlide 0.4s ease-out;
        }

        .polish-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header-accent {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body-content {
            padding: 35px 30px;
        }

        .form-grid-simple {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
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

        .input-simple {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 14px;
            color: #1e293b;
            transition: border-color 0.2s;
        }

        .input-simple:focus {
            outline: none;
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.05);
        }

        .input-simple:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
        }

        .section-header-simple {
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-update-gov {
            background: #0038A8;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-update-gov:hover {
            background: #002366;
        }

        .profile-header-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .profile-header-desc { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .profile-header-icon { font-size: 24px; color: #0038A8; opacity: 0.5; }
        .field-error { color: #ef4444; font-size: 11px; }
        .input-capitalize { text-transform: capitalize; }
        .section-icon { font-size: 12px; }
        .form-actions { margin-top: 40px; display: flex; justify-content: flex-end; gap: 15px; padding-top: 25px; border-top: 1px solid #f1f5f9; }
        .btn-cancel { padding: 12px 25px; text-decoration: none; color: #64748b; font-weight: 700; font-size: 14px; }
        @media (max-width: 767px) {
            .form-grid-simple { grid-template-columns: 1fr !important; gap: 15px !important; }
            .form-actions { flex-direction: column !important; gap: 10px !important; }
            .form-actions a,
            .form-actions button { width: 100% !important; text-align: center !important; justify-content: center !important; }
            .btn-cancel { display: block !important; padding: 12px !important; border: 1px solid #cbd5e1 !important; border-radius: 4px !important; color: #475569 !important; background: #f8fafc !important; min-height: 48px !important; line-height: 24px !important; }
            .btn-update-gov { width: 100% !important; min-height: 48px !important; font-size: 15px !important; }
            input:not([type="checkbox"]):not([type="radio"]), select, textarea { min-height: 48px !important; font-size: 15px !important; }
            .profile-header { flex-direction: column !important; align-items: flex-start !important; gap: 6px !important; }
            .profile-header-title { font-size: 16px !important; }
        }
    </style>
@endsection

@section('content')
<div class="profile-container">
    
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="profile-header-title">Account Configuration</h3>
                <p class="profile-header-desc">Manage your personnel profile and security credentials.</p>
            </div>
            <i class="fa-solid fa-user-gear profile-header-icon"></i>
        </div>

        <!-- FORM BODY -->
        <form action="{{ route('profile.update') }}" method="POST" class="card-body-content">
            @csrf
            @method('PUT')

            <div class="form-grid-simple">
                <div>
                    <label class="label-simple">Full Name</label>
                    <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="input-simple" required>
                    @error('full_name') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label-simple">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input-simple" required>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label-simple">Position / Designation</label>
                    <input type="text" name="position" value="{{ old('position', $user->position) }}" class="input-simple">
                </div>

                <div>
                    <label class="label-simple">Division / Office</label>
                    <input type="text" value="{{ old('office', $user->office) }}" class="input-simple" disabled>
                </div>

                <div>
                    <label class="label-simple">Department</label>
                    <input type="text" value="{{ old('department', $user->department) }}" class="input-simple" disabled>
                </div>

                <div>
                    <label class="label-simple">System Role</label>
                    <input type="text" value="{{ str_replace('_', ' ', $user->role) }}" class="input-simple input-capitalize" disabled>
                </div>
            </div>

            <div class="section-header-simple">
                <i class="fa-solid fa-key section-icon"></i>
                Authentication Security
            </div>

            <div class="form-grid-simple">
                <div>
                    <label class="label-simple">New Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current" class="input-simple">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label-simple">Confirm New Password</label>
                    <input type="password" name="password_confirmation" placeholder="Verify new password" class="input-simple">
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route(Auth::user()->dashboardRouteName()) }}" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-update-gov">Update Profile</button>
            </div>
        </form>
    </div>

</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
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
