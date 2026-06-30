<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Satisfaction Measurement (CSM)</title>

    <!-- Favicon / Logo sa tab -->
    <link rel="icon" href="{{ asset('csm/NCMB_LOGO.png') }}" type="image/png">

    <link rel="stylesheet" href="{{ asset('csm/csm_survey.css') }}">
    <link rel="stylesheet" href="{{ asset('csm/csm_survey_responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('csm/csm_survey_modal.css') }}">
    <link rel="stylesheet" href="{{ asset('csm/consent_see_more.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style nonce="{{ $cspNonce }}">
        .csm-description { margin-top: 10px; font-size: 14px; color: #334155; }
        .csm-info-msg { margin-top: 8px; padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; font-size: 13px; color: #1e40af; }
        .required-star { color: red; }
        .cc-error { color: red; display: none; text-align: center; font-weight: bold; margin-top: 10px; }
        .sqd-error { color: red; display: none; text-align: center; font-weight: bold; margin: 0; }
        .td-left { text-align: left; }
    </style>

    <script nonce="{{ $cspNonce }}" src="{{ asset('csm/csm_survey.js') }}"></script>
    <script nonce="{{ $cspNonce }}" src="{{ asset('csm/disabled_button.js') }}" defer></script>
    <script nonce="{{ $cspNonce }}" src="{{ asset('csm/consent_see_more.js') }}" defer></script>
</head>

<body>
    <!-- Form Container -->
    <div class="form-container">
        <!-- Survey Form -->
        <form id="csmForm" action="{{ route('csm.store') }}" method="POST">
            @csrf
            <input type="hidden" name="request_id" value="{{ $ticket->id }}">

            <!-- ✅ Title Banner -->
            <div class="title-image">
                <img src="{{ asset('csm/csm_banner.png') }}" alt="csm banner" class="banner-image">
            </div>
            
            <div class="form-body">
                <!-- Survey Title -->
                <h1>THANK YOU FOR GIVING US THE OPPORTUNITY TO SERVE YOU!</h1>
                <p class="csm-description"><strong>This survey is required</strong> to finish your completed request ({{ $ticket->display_number ?? $ticket->request_number }} — {{ $ticket->type }}).</p>
                @if(session('info'))
                    <p class="csm-info-msg">{{ session('info') }}</p>
                @endif
                

                <!-- Survey Description -->
                <p><strong>Please help us improve the quality of our services by taking a few minutes to answer this survey.
                    <br><br>
                    This Client Satisfaction Measurement (CSM) survey assesses customer experience in government offices. Your feedback on your recent transaction with us will help us improve our services to the public.
                    </strong>
                    <br><br>
                    
                    <div class="consent-text">
                        <!-- Consent Notice -->
                        <strong>CONSENT NOTICE:</strong>
                        <br>
                        <div class="consent-content">
                            <strong>
                                Personal information shared shall be kept confidential. The respondents have the option not to answer this form. By CLICKING "YES", the respondent grants his/her voluntary and absolute consent to the collection and processing of his/her personal data (as defined) and other information or records given/shared by him/her or by his/her authorized agent/representative/s to the National Conciliation and Mediation Board (NCMB) and/or any of its authorized agent/s or representative/s solely for purposes relating to program implementation and reporting in accordance with Republic Act (R.A) 10173, otherwise known as the 'Data Privacy Act of 2012" and its implementing Rules and Regulations (IRR). Furthermore, the respondent agrees to hold the NCMB free from any liability arising from the lawful disclosure and use of the collected data and information in accordance with relevant privacy laws and policies. The NCMB shall maintain strict confidentiality of the collected data and information, and retain these until the purpose for which they were collected has been achieved.
                            </strong>
                            <strong class="required-star"> *</strong>
                        </div>
                        <div class="fade-overlay"></div>
                        <button type="button" class="toggle-btn">
                            <span>See More</span>
                            <span class="arrow">&#x25BC;</span>
                        </button>
                        <br><br>
                    </div>
                    
                    <!-- Consent Radio Buttons -->
                    <label>
                        <input type="radio" name="consent" value="yes" required> <strong>Yes, I agree</strong>
                    </label>
                    <br><br>
                    <label>
                        <input type="radio" name="consent" value="no" required> <strong>No, I do not agree</strong>
                    </label>
                </p>
                
                <hr class="divider">
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <strong class="required-star">* Indicates required question</strong>
                    </div>
                </div>
                
                <!-- First Row: Email (Optional) -->
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="email"><strong>Email address (optional):</strong></label>
                        <input type="email" id="email" name="email" placeholder="Type your email here..." value="" autocomplete="off">
                    </div>
                </div>
                
                <!-- Second Row: Age & Sex -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="age"><strong>Age</strong>:<strong class="required-star"> *</strong></label>
                        <input type="number" id="age" name="age" placeholder="Your age" required min="18" max="99">
                    </div>
                    <div class="form-group">
                        <label for="sex"><strong>Sex</strong>:<strong class="required-star"> *</strong></label>
                        <select id="sex" name="sex" required>
                            <option value="" disabled selected>Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>

                <!-- Third Row: Office & Client Type -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="office"><strong>Office</strong>:<strong class="required-star"> *</strong></label>
                        <input type="text" id="office" name="office_display" value="{{ Auth::user()->office }}" readonly class="disabled-gray">
                    </div>
                    <div class="form-group">
                        <label for="client_type"><strong>Client Type</strong>:<strong class="required-star"> *</strong></label>
                        <select id="client_type" name="client_type" required>
                            <option value="Government" selected>Government</option>
                            <option value="Citizen">Citizen</option>
                            <option value="Business">Business</option>
                        </select>
                    </div>
                </div>
                
                <!-- Fourth Row: Service Availed & Last Service Availed -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_availed"><strong>Service Availed</strong>:<strong class="required-star"> *</strong></label>
                        <input type="text" id="service_availed" name="service_availed" value="{{ $ticket->type }}" readonly class="disabled-gray">
                    </div>
                    <div class="form-group">
                        <label for="last_service_availed"><strong>Date Availed</strong>:<strong class="required-star"> *</strong></label>
                        <input type="date" id="last_service_availed" name="last_service_availed" value="{{ $ticket->created_at->format('Y-m-d') }}" readonly class="disabled-gray">
                    </div>
                </div>

                
                <hr class="divider">
                
                <div class="instructions">
                    <p>
                        <strong>INSTRUCTIONS: Check mark (✔) your answer to the Citizen's Charter (CC) questions. The Citizen's Charter is an official document that outlines the services provided by a government agency/office including its requirements, fees, and processing times among others.</strong>
                    </p>
                </div>
                    
                <div class="survey-section">
                    <div class="survey-header">
                        <p><strong>CC1</strong></p>
                        <p><strong>Which of the following best describes your awareness of a CC?</strong> <strong class="required-star"> *</strong></p>
                    </div>
                    <div class="survey-options">
                        <label>                        <input type="checkbox" name="cc1[]" value="1" data-only-one="cc1"> 1. I know what a CC is and I saw this office's CC.</label>
                        <label><input type="checkbox" name="cc1[]" value="2" data-only-one="cc1"> 2. I know what a CC is but I did NOT see this office's CC.</label>
                        <label><input type="checkbox" name="cc1[]" value="3" data-only-one="cc1"> 3. I learned of the CC only when I saw this office's CC.</label>
                        <label><input type="checkbox" name="cc1[]" value="4" data-only-one="cc1"> 4. I do not know what a CC is and I did not see one in this office.</label>
                    </div>
                    <p class="cc1-error cc-error">Please select an option for CC1.</p>
                </div>
                
                <div class="survey-section">
                    <div class="survey-header">
                        <p><strong>CC2</strong></p>
                        <p><strong>If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...?</strong> <strong class="required-star"> *</strong></p>
                    </div>
                    <div class="survey-options">
                        <div class="survey-row">
                            <div class="left-group">
                                <label><input type="checkbox" name="cc2[]" value="1" data-only-one="cc2"> 1. Easy to see</label>
                                <label><input type="checkbox" name="cc2[]" value="2" data-only-one="cc2"> 2. Somewhat easy to see</label>
                                <label><input type="checkbox" name="cc2[]" value="3" data-only-one="cc2"> 3. Difficult to see</label>
                            </div>
                            <div class="right-group">
                                <label><input type="checkbox" name="cc2[]" value="4" data-only-one="cc2"> 4. Not visible at all</label>
                                <label><input type="checkbox" name="cc2[]" value="5" data-only-one="cc2"> 5. N/A</label>
                            </div>
                        </div>
                    </div>
                    <p class="cc2-error cc-error">Please select an option for CC2.</p>
                </div>
                
                <div class="survey-section">
                    <div class="survey-header">
                        <p><strong>CC3</strong></p>
                        <p><strong>If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</strong> <strong class="required-star"> *</strong></p>
                    </div>
                    <div class="survey-options">
                        <div class="survey-row">
                            <div class="left-group">
                                <label><input type="checkbox" name="cc3[]" value="1" data-only-one="cc3"> 1. Helped very much</label>
                                <label><input type="checkbox" name="cc3[]" value="2" data-only-one="cc3"> 2. Somewhat helped</label>
                            </div>
                            <div class="right-group">
                                <label><input type="checkbox" name="cc3[]" value="3" data-only-one="cc3"> 3. Did not help</label>
                                <label><input type="checkbox" name="cc3[]" value="4" data-only-one="cc3"> 4. N/A</label>
                            </div>
                        </div>
                    </div>
                    <p class="cc3-error cc-error">Please select an option for CC3.</p>
                </div>
                
                <hr class="divider">
                
                <div class="instructions">
                    <p>
                        <strong>INSTRUCTIONS: Please put a check mark (✔) on the column that best corresponds to your answer.</strong>
                    </p>
                </div>
                
                <!-- Start of survey table -->
                <div class="survey-table">
                    <table>
                        <thead>
                            <tr>
                                <th></th>
                                <th>
                                    <div class="header-cell">
                                        <img src="{{ asset('csm/strongly disagree.png') }}" alt="strongly disagree" class="emoji">
                                        <span>Strongly Disagree</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="header-cell">
                                        <img src="{{ asset('csm/disagree.png') }}" alt="disagree" class="emoji">
                                        <span>Disagree</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="header-cell">
                                        <img src="{{ asset('csm/neither agree nor disagree.png') }}" alt="neither agree nor disagree" class="emoji">
                                        <span>Neither Agree nor Disagree</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="header-cell">
                                        <img src="{{ asset('csm/agree.png') }}" alt="agree" class="emoji">
                                        <span>Agree</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="header-cell">
                                        <img src="{{ asset('csm/strongly agree.png') }}" alt="strongly agree" class="emoji">
                                        <span>Strongly Agree</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="header-cell">
                                        <span class="spacer"></span>
                                        <span>N/A</span>
                                        <span class="spacer"></span>
                                        <span class="subtext">Not Applicable</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        
                        <tbody>
                            @php
                                $sqdQuestions = [
                                    'sqd1' => 'SDQ0. I am satisfied with the service that I availed.',
                                    'sqd2' => 'SDQ1. I spent a reasonable amount of time for my transaction.',
                                    'sqd3' => 'SDQ2. The office followed the transaction\'s requirements and steps based on the information provided.',
                                    'sqd4' => 'SDQ3. The steps (including payment) I needed to do for my transaction were easy and simple.',
                                    'sqd5' => 'SDQ4. I easily found information about my transaction from the office\'s website.',
                                    'sqd6' => 'SDQ5. I paid a reasonable amount of fees for my transaction.',
                                    'sqd7' => 'SDQ6. I am confident my online transaction was secure.',
                                    'sqd8' => 'SDQ7. The office\'s online support was available, and (if asked questions) online support was quick to respond.',
                                    'sqd9' => 'SDQ8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.'
                                ];
                            @endphp
                            @foreach($sqdQuestions as $key => $question)
                            <tr>
                                <td class="td-left"><strong>{{ $question }}</strong><strong class="required-star"> *</strong></td>
                                <td><input type="checkbox" name="{{ $key }}" value="Strongly Disagree" data-only-row="{{ $key }}"></td>
                                <td><input type="checkbox" name="{{ $key }}" value="Disagree" data-only-row="{{ $key }}"></td>
                                <td><input type="checkbox" name="{{ $key }}" value="Neither Agree Nor Disagree" data-only-row="{{ $key }}"></td>
                                <td><input type="checkbox" name="{{ $key }}" value="Agree" data-only-row="{{ $key }}"></td>
                                <td><input type="checkbox" name="{{ $key }}" value="Strongly Agree" data-only-row="{{ $key }}"></td>
                                <td><input type="checkbox" name="{{ $key }}" value="N/A" data-only-row="{{ $key }}"></td>
                            </tr>
                            <tr><td colspan="7"><p class="{{ $key }}-error sqd-error">Please select an option.</p></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <hr class="divider">

                <!-- Complaint Box Section -->
                <div class="suggestion-box">
                    <label for="suggestions">Suggestions on how we can further improve our services (optional):</label>
                    <textarea id="suggestions" name="suggestions" rows="4" placeholder="Type your suggestions here..."></textarea>
                </div>
                                                        
                <!-- Submit Button Section -->
                <div class="submit-container">
                    <button type="submit" class="submit-btn" id="submitBtn">
                        Submit
                    </button>
                </div>
            </div>
        </form>
    </div>
<script nonce="{{ $cspNonce }}">
document.getElementById('csmForm').addEventListener('submit', function(e) {
    if (!validateForm(e)) { e.preventDefault(); }
});
document.addEventListener('change', function(e) {
    var cb = e.target;
    if (cb.type === 'checkbox') {
        var group = cb.dataset.onlyOne;
        if (group) {
            document.querySelectorAll('[data-only-one="' + group + '"]').forEach(function(c) {
                if (c !== cb) c.checked = false;
            });
        }
        var row = cb.dataset.onlyRow;
        if (row) {
            document.querySelectorAll('[data-only-row="' + row + '"]').forEach(function(c) {
                if (c !== cb) c.checked = false;
            });
        }
    }
});
</script>
</body>
</html>