# CMMS System Flowcharts
## For Presentation

---

# PM SCHEDULE FLOW
### (Preventive Maintenance)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          PM GENERATION CYCLE                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  SUPER ADMIN                                                            │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  1. Create PM Schedule                                          │   │
│  │     ├─ Schedule Name: "All Divisions PM Cycle"                  │   │
│  │     ├─ Division Filter: All Divisions / RID / AD / etc.         │   │
│  │     └─ Frequency: Monthly / Quarterly / Semi-annual / Annual    │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  2. Click "Generate PM"                                        │   │
│  │     → System checks: Is there already a running cycle?         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  SYSTEM                                                          │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│  ┌────────┴────────────────────────────────────────────────────────┐   │
│  │  3. Check Eligible Users                                        │   │
│  │     ├─ Get all ACTIVE assets with assigned users                │   │
│  │     ├─ Filter by division (if specified)                        │   │
│  │     ├─ Group assets by user (1 user = 1 PM bundle)             │   │
│  │     ├─ Remove users who already completed PM this cycle        │   │
│  │     ├─ Check if user is DUE for PM (based on frequency)        │   │
│  │     └─ Group eligible users by DIVISION                         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│  ┌────────┴────────────────────────────────────────────────────────┐   │
│  │  4. Find Focus Division                                         │   │
│  │     → Which division has the OLDEST asset? Goes FIRST            │   │
│  │     → Example: RID (Jan 2020) → AD (Mar 2021) → FMD (Jun 2022) │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│  ┌────────┴────────────────────────────────────────────────────────┐   │
│  │  5. GENERATE PM (BATCH)                                         │   │
│  │     → Create PM tickets for ALL users in focus division         │   │
│  │     → Each user gets:                                           │   │
│  │        ├─ PreventiveMaintenance record                          │   │
│  │        ├─ Request ticket (Status: Scheduled)                    │   │
│  │        ├─ User's assets mapped to PM form                       │   │
│  │        └─ Notification sent to user                             │   │
│  │     → Mark division as "IN PROGRESS"                            │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  IT PERSONNEL                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  6. Conduct PM                                                  │   │
│  │     ├─ View PM Tasks (from Dashboard or PM Tasks page)          │   │
│  │     ├─ Open PM form                                            │   │
│  │     ├─ Fill checklist (Monitor, CPU, Keyboard, Mouse, etc.)     │   │
│  │     ├─ Add findings/remarks                                     │   │
│  │     ├─ Request parts if needed (via Requisition)               │   │
│  │     └─ Update status: Scheduled → Ongoing → Completed           │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  SYSTEM                                                                │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  7. Auto-Advance                                                │   │
│  │     → Check: Are ALL users in current division Completed?        │   │
│  │     ├─ YES → Auto-advance to NEXT division                      │   │
│  │     │       → Generate PM for next division                     │   │
│  │     │       → Repeat until all divisions done                   │   │
│  │     │       → All done? Push next schedule date                 │   │
│  │     └─ NO  → Wait for IT to complete remaining PMs              │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  CYCLE CONTROLS (Super Admin):                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  [⏸ Pause]   → Halts auto-advance (IT can still conduct)       │   │
│  │  [▶ Resume]  → Restores auto-advance                           │   │
│  │  [⏹ Stop]    → Ends entire cycle                               │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PM STATUS FLOW                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│    ┌────────────┐     ┌──────────┐     ┌────────────┐                   │
│    │ SCHEDULED  │────►│ ONGOING  │────►│ COMPLETED  │                   │
│    └────────────┘     └──────────┘     └────────────┘                   │
│          │                                                            │
│          ├──► CANCELLED                                                │
│          └──► REJECTED                                                 │
│                                                                         │
│  Note: PM tickets are auto-generated as "Scheduled"                     │
│        IT updates to "Ongoing" when starting PM                         │
│        IT updates to "Completed" when PM is done                       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# ICT REQUEST FLOW
### (Repair / Replacement)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ICT REQUEST PROCESS                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  END USER                                                               │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  1. Submit ICT Request                                          │   │
│  │     ├─ Select asset to repair (from assigned assets)            │   │
│  │     ├─ Describe the problem                                     │   │
│  │     ├─ Fill personal info (name, division, email)               │   │
│  │     └─ Sign & submit                                            │   │
│  │     → Status: PENDING                                            │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  DIVISION ADMIN                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  2. Review Request                                              │   │
│  │     → View full ticket details                                   │   │
│  │     ├─ [APPROVE] → Forward to Super Admin                       │   │
│  │     │              → Notify User: "Request forwarded"            │   │
│  │     └─ [REJECT]  → With reason                                  │   │
│  │                    → Notify User: "Request rejected"             │   │
│  │                    → Status: REJECTED                            │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼ (if Approved)                                              │
│  SUPER ADMIN                                                           │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  3. Assign IT Personnel                                        │   │
│  │     ├─ View ticket details (Job Order hub)                     │   │
│  │     ├─ Select IT staff to assign                               │   │
│  │     ├─ Or assign self as technician                            │   │
│  │     └─ When assigned → Status: ONGOING                         │   │
│  │     → Notifications sent to IT and End User                    │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  IT PERSONNEL                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  4. Diagnose & Assess                                           │   │
│  │     ├─ View assigned tickets (from Dashboard)                   │   │
│  │     ├─ Open ICT Form                                            │   │
│  │     ├─ Fill Section 2:                                          │   │
│  │     │   ├─ Nature of Problem (Hardware/Software/Network/etc.)   │   │
│  │     │   ├─ Findings / Diagnosis                                 │   │
│  │     │   └─ Recommendation (For Repair/Replacement/Disposal)     │   │
│  │     ├─ If parts needed → Create REQUISITION                     │   │
│  │     │   → Status: AWAITING PARTS                                │   │
│  │     │   → Supply Admin reviews and approves/rejects             │   │
│  │     └─ Save as draft or continue                                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│  ┌────────┴────────────────────────────────────────────────────────┐   │
│  │  5. Complete Repair                                             │   │
│  │     ├─ Add IT Technician Signature                              │   │
│  │     ├─ Get End User Acknowledgment (signature)                  │   │
│  │     └─ Mark as COMPLETED                                        │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│           │                                                            │
│           ▼                                                            │
│  SYSTEM                                                                │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  6. Post-Completion                                             │   │
│  │     ├─ User notified: "Your request has been completed"          │   │
│  │     ├─ Asset record updated (if needed)                         │   │
│  │     ├─ Audit log recorded                                       │   │
│  │     └─ CSM Survey sent to user                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ICT STATUS FLOW                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│    ┌──────────┐                                                         │
│    │ PENDING  │──┬──► (Admin Review)                                    │
│    └──────────┘  │                                                      │
│                  ├──► APPROVED → PENDING (still, waiting Super Admin)   │
│                  ├──► REJECTED → (terminal)                            │
│                  └──► CANCELLED → (terminal)                           │
│                                                                         │
│    ONGOING ◄──── (IT assigned)                                          │
│       │                                                                 │
│       ├──► AWAITING PARTS (if parts needed)                            │
│       │       └──► ONGOING (parts received)                            │
│       │                                                                 │
│       └──► COMPLETED (terminal)                                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# COMPARISON: PM vs ICT

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PM SCHEDULE         │       ICT REQUEST         │
├─────────────────────────────────────────────────────────────────────────┤
│ Trigger      │ Scheduled (auto)             │ On-demand (user reports)  │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Created by   │ Super Admin (schedule)       │ End User (request)        │
│              │ System (generates tickets)   │                          │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Review       │ None needed                  │ Division Admin           │
│ Needed?      │ (auto-generated)             │ → Super Admin            │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Frequency    │ Monthly / Quarterly /        │ Anytime (when broken)    │
│              │ Semi-annual / Annual         │                          │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Purpose      │ Preventive Maintenance       │ Repair / Replacement     │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Start Status │ Scheduled                    │ Pending                  │
│──────────────┼──────────────────────────────┼──────────────────────────│
│ Parts        │ Optional (requisition)       │ Optional (requisition)   │
│ Needed?      │                              │                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# ROLES & RESPONSIBILITIES

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ROLE             │  PM SCHEDULE              │  ICT REQUEST            │
├─────────────────────────────────────────────────────────────────────────┤
│                   │                           │                         │
│  END USER         │ • Receive notification    │ • Submit ICT request    │
│  (Employee)       │ • Acknowledge completion  │ • Track ticket status   │
│                   │ • Fill CSM survey         │ • Acknowledge repair    │
│                   │                           │ • Fill CSM survey       │
│                   │                           │                         │
│  DIVISION ADMIN   │ — Not involved —          │ • Review ICT requests   │
│                   │                           │ • Approve / Reject      │
│                   │                           │ • Forward to Super Admin│
│                   │                           │                         │
│  SUPER ADMIN      │ • Create PM Schedule     │ • View approved requests│
│                   │ • Generate PM cycle       │ • Assign IT personnel   │
│                   │ • Pause / Resume / Stop   │ • Monitor progress      │
│                   │                           │ • Oversee operations    │
│                   │                           │                         │
│  IT PERSONNEL     │ • Conduct PM (view tasks, │ • Diagnose issue        │
│                   │   fill checklist, update  │ • Fill assessment form  │
│                   │   status, request parts)  │ • Request parts         │
│                   │                           │ • Complete repair       │
│                   │                           │                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# SYSTEM OVERVIEW

```
                         ┌──────────────────────┐
                         │                      │
                         │     END USER         │
                         │    (Employee)        │
                         │                      │
                         └──────────┬───────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
          ┌──────────────────┐           ┌──────────────────┐
          │                  │           │                  │
          │   ICT REQUEST    │           │   PM SCHEDULE    │
          │  (Repair/Issue)  │           │  (Maintenance)   │
          │                  │           │                  │
          └────────┬─────────┘           └────────┬─────────┘
                   │                              │
                   ▼                              │
          ┌──────────────────┐                    │
          │                  │                    │
          │ DIVISION ADMIN   │                    │
          │ Review / Approve │                    │
          │                  │                    │
          └────────┬─────────┘                    │
                   │                              │
                   ▼                              │
          ┌──────────────────┐                    │
          │                  │                    │
          │  SUPER ADMIN     │                    │
          │  Assign IT       │                    │
          │                  │                    │
          └────────┬─────────┘                    │
                   │                              │
                   └──────────────┬───────────────┘
                                  │
                                  ▼
                        ┌──────────────────┐
                        │                  │
                        │  IT PERSONNEL    │
                        │                  │
                        │ • Conduct PM     │
                        │ • Repair ICT     │
                        │ • Update status  │
                        │ • Request parts  │
                        │                  │
                        └────────┬─────────┘
                                 │
                                 ▼
                        ┌──────────────────┐
                        │                  │
                        │    COMPLETED     │
                        │                  │
                        │ • Asset updated  │
                        │ • User notified  │
                        │ • CSM Survey     │
                        │                  │
                        └──────────────────┘