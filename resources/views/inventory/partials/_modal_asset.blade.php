<!-- ADD/EDIT ASSET MODAL -->
<x-modal id="assetModal" title="Register Asset" titleId="assetModalTitle" hideClose="true">
        <form id="assetForm" class="modal-form">
            <input type="hidden" id="assetId" value="">
            <div class="modal-body">
                {{-- Edit info banner ΓÇö only shown when editing an existing asset --}}
                <div id="editAssetBanner" class="edit-banner">
                    <div class="edit-banner-title">
                        <i class="fa-solid fa-pen-to-square"></i> Editing Existing Asset
                    </div>
                    <div class="edit-banner-info">
                        <div><span class="edit-label">Item:</span> <strong id="editBannerName" class="edit-value"></strong></div>
                        <div><span class="edit-label">SN:</span> <span id="editBannerSN" class="font-mono edit-sn"></span></div>
                        <div><span class="edit-label">Custodian:</span> <strong id="editBannerCustodian" class="edit-custodian"></strong></div>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label-gov">Asset Category</label>
                        <select id="assetCategory" required class="form-input-gov">
                            <option value="Desktop">Desktop</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Monitor">Monitor</option>
                            <option value="Printer/Scanner">Printer/Scanner</option>
                            <option value="Peripherals">Peripherals</option>
                            <option value="Network/Server">Network/Server</option>
                            <option value="IT Parts / Components">IT Parts / Components</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-gov">Item Model / Name</label>
                        <input type="text" id="assetItemName" required placeholder="e.g., HP ProBook 440 G8" class="form-input-gov">
                    </div>
                </div>

                <!-- DYNAMIC SPECS CONTAINER (Desktop / Laptop) -->
                <div id="dynamicSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Hardware Specifications</label>
                    <div class="form-grid-3">

                        {{-- OS --}}
                        <div>
                            <label class="spec-label">Operating System</label>
                            <select id="specOs" class="form-input-gov">
                                <option value="">-- Select OS --</option>
                                <option value="Windows 10">Windows 10</option>
                                <option value="Windows 11">Windows 11</option>
                                <option value="macOS">macOS</option>
                                <option value="Linux">Linux</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- CPU --}}
                        <div class="col-span-2">
                            <label class="spec-label">Processor (CPU)</label>
                            <select id="specCpu" class="form-input-gov">
                                <option value="">-- Select CPU --</option>
                                <optgroup label="Intel Core i3">
                                    <option value="Intel Core i3-8100">Intel Core i3-8100 (8th Gen)</option>
                                    <option value="Intel Core i3-9100">Intel Core i3-9100 (9th Gen)</option>
                                    <option value="Intel Core i3-10100">Intel Core i3-10100 (10th Gen)</option>
                                    <option value="Intel Core i3-12100">Intel Core i3-12100 (12th Gen)</option>
                                    <option value="Intel Core i3-13100">Intel Core i3-13100 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i5">
                                    <option value="Intel Core i5-8400">Intel Core i5-8400 (8th Gen)</option>
                                    <option value="Intel Core i5-8500">Intel Core i5-8500 (8th Gen)</option>
                                    <option value="Intel Core i5-9400">Intel Core i5-9400 (9th Gen)</option>
                                    <option value="Intel Core i5-10400">Intel Core i5-10400 (10th Gen)</option>
                                    <option value="Intel Core i5-10500">Intel Core i5-10500 (10th Gen)</option>
                                    <option value="Intel Core i5-11400">Intel Core i5-11400 (11th Gen)</option>
                                    <option value="Intel Core i5-12400">Intel Core i5-12400 (12th Gen)</option>
                                    <option value="Intel Core i5-12500">Intel Core i5-12500 (12th Gen)</option>
                                    <option value="Intel Core i5-13400">Intel Core i5-13400 (13th Gen)</option>
                                    <option value="Intel Core i5-13500">Intel Core i5-13500 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i7">
                                    <option value="Intel Core i7-8700">Intel Core i7-8700 (8th Gen)</option>
                                    <option value="Intel Core i7-9700">Intel Core i7-9700 (9th Gen)</option>
                                    <option value="Intel Core i7-10700">Intel Core i7-10700 (10th Gen)</option>
                                    <option value="Intel Core i7-11700">Intel Core i7-11700 (11th Gen)</option>
                                    <option value="Intel Core i7-12700">Intel Core i7-12700 (12th Gen)</option>
                                    <option value="Intel Core i7-13700">Intel Core i7-13700 (13th Gen)</option>
                                    <option value="Intel Core i7-14700">Intel Core i7-14700 (14th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i9">
                                    <option value="Intel Core i9-9900K">Intel Core i9-9900K (9th Gen)</option>
                                    <option value="Intel Core i9-10900">Intel Core i9-10900 (10th Gen)</option>
                                    <option value="Intel Core i9-12900">Intel Core i9-12900 (12th Gen)</option>
                                    <option value="Intel Core i9-13900">Intel Core i9-13900 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 3">
                                    <option value="AMD Ryzen 3 3100">AMD Ryzen 3 3100 (3000 Series)</option>
                                    <option value="AMD Ryzen 3 3300X">AMD Ryzen 3 3300X (3000 Series)</option>
                                    <option value="AMD Ryzen 3 4100">AMD Ryzen 3 4100 (4000 Series)</option>
                                    <option value="AMD Ryzen 3 5300G">AMD Ryzen 3 5300G (5000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 5">
                                    <option value="AMD Ryzen 5 3400G">AMD Ryzen 5 3400G (3000 Series)</option>
                                    <option value="AMD Ryzen 5 3600">AMD Ryzen 5 3600 (3000 Series)</option>
                                    <option value="AMD Ryzen 5 5500">AMD Ryzen 5 5500 (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600">AMD Ryzen 5 5600 (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600G">AMD Ryzen 5 5600G (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600X">AMD Ryzen 5 5600X (5000 Series)</option>
                                    <option value="AMD Ryzen 5 7600">AMD Ryzen 5 7600 (7000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 7">
                                    <option value="AMD Ryzen 7 3700X">AMD Ryzen 7 3700X (3000 Series)</option>
                                    <option value="AMD Ryzen 7 5700G">AMD Ryzen 7 5700G (5000 Series)</option>
                                    <option value="AMD Ryzen 7 5700X">AMD Ryzen 7 5700X (5000 Series)</option>
                                    <option value="AMD Ryzen 7 5800X">AMD Ryzen 7 5800X (5000 Series)</option>
                                    <option value="AMD Ryzen 7 7700">AMD Ryzen 7 7700 (7000 Series)</option>
                                    <option value="AMD Ryzen 7 7700X">AMD Ryzen 7 7700X (7000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 9">
                                    <option value="AMD Ryzen 9 5900X">AMD Ryzen 9 5900X (5000 Series)</option>
                                    <option value="AMD Ryzen 9 5950X">AMD Ryzen 9 5950X (5000 Series)</option>
                                    <option value="AMD Ryzen 9 7900X">AMD Ryzen 9 7900X (7000 Series)</option>
                                    <option value="AMD Ryzen 9 7950X">AMD Ryzen 9 7950X (7000 Series)</option>
                                </optgroup>
                                <option value="Other">Other (specify in notes)</option>
                            </select>
                        </div>

                        {{-- RAM --}}
                        <div>
                            <label class="spec-label">Memory (RAM)</label>
                            <select id="specRam" class="form-input-gov">
                                <option value="">-- Select RAM --</option>
                                <option value="4GB DDR3">4GB DDR3</option>
                                <option value="8GB DDR3">8GB DDR3</option>
                                <option value="4GB DDR4">4GB DDR4</option>
                                <option value="8GB DDR4">8GB DDR4</option>
                                <option value="16GB DDR4">16GB DDR4</option>
                                <option value="32GB DDR4">32GB DDR4</option>
                                <option value="64GB DDR4">64GB DDR4</option>
                                <option value="8GB DDR5">8GB DDR5</option>
                                <option value="16GB DDR5">16GB DDR5</option>
                                <option value="32GB DDR5">32GB DDR5</option>
                                <option value="64GB DDR5">64GB DDR5</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- Primary Storage --}}
                        <div>
                            <label class="spec-label">Primary Storage</label>
                            <select id="specHd1" class="form-input-gov">
                                <option value="">-- Select Storage --</option>
                                <optgroup label="M.2 NVMe SSD">
                                    <option value="128GB M.2 NVMe SSD">128GB M.2 NVMe SSD</option>
                                    <option value="256GB M.2 NVMe SSD">256GB M.2 NVMe SSD</option>
                                    <option value="512GB M.2 NVMe SSD">512GB M.2 NVMe SSD</option>
                                    <option value="1TB M.2 NVMe SSD">1TB M.2 NVMe SSD</option>
                                    <option value="2TB M.2 NVMe SSD">2TB M.2 NVMe SSD</option>
                                </optgroup>
                                <optgroup label="M.2 SATA SSD">
                                    <option value="128GB M.2 SATA SSD">128GB M.2 SATA SSD</option>
                                    <option value="256GB M.2 SATA SSD">256GB M.2 SATA SSD</option>
                                    <option value="512GB M.2 SATA SSD">512GB M.2 SATA SSD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; SATA SSD">
                                    <option value="120GB 2.5&quot; SSD">120GB 2.5" SSD</option>
                                    <option value="240GB 2.5&quot; SSD">240GB 2.5" SSD</option>
                                    <option value="480GB 2.5&quot; SSD">480GB 2.5" SSD</option>
                                    <option value="1TB 2.5&quot; SSD">1TB 2.5" SSD</option>
                                </optgroup>
                                <optgroup label="3.5&quot; SATA HDD">
                                    <option value="500GB 3.5&quot; HDD">500GB 3.5" HDD</option>
                                    <option value="1TB 3.5&quot; HDD">1TB 3.5" HDD</option>
                                    <option value="2TB 3.5&quot; HDD">2TB 3.5" HDD</option>
                                    <option value="4TB 3.5&quot; HDD">4TB 3.5" HDD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; HDD (Laptop)">
                                    <option value="500GB 2.5&quot; HDD">500GB 2.5" HDD</option>
                                    <option value="1TB 2.5&quot; HDD">1TB 2.5" HDD</option>
                                </optgroup>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- Secondary Storage --}}
                        <div>
                            <label class="spec-label">Secondary Storage (optional)</label>
                            <select id="specHd2" class="form-input-gov">
                                <option value="None">None</option>
                                <optgroup label="M.2 NVMe SSD">
                                    <option value="256GB M.2 NVMe SSD">256GB M.2 NVMe SSD</option>
                                    <option value="512GB M.2 NVMe SSD">512GB M.2 NVMe SSD</option>
                                    <option value="1TB M.2 NVMe SSD">1TB M.2 NVMe SSD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; SSD">
                                    <option value="240GB 2.5&quot; SSD">240GB 2.5" SSD</option>
                                    <option value="480GB 2.5&quot; SSD">480GB 2.5" SSD</option>
                                    <option value="1TB 2.5&quot; SSD">1TB 2.5" SSD</option>
                                </optgroup>
                                <optgroup label="3.5&quot; HDD">
                                    <option value="500GB 3.5&quot; HDD">500GB 3.5" HDD</option>
                                    <option value="1TB 3.5&quot; HDD">1TB 3.5" HDD</option>
                                    <option value="2TB 3.5&quot; HDD">2TB 3.5" HDD</option>
                                    <option value="4TB 3.5&quot; HDD">4TB 3.5" HDD</option>
                                </optgroup>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- GPU --}}
                        <div class="col-span-2">
                            <label class="spec-label">Graphics Card (GPU)</label>
                            <select id="specGpu" class="form-input-gov">
                                <option value="">-- Select GPU --</option>
                                <optgroup label="Integrated Graphics">
                                    <option value="Intel UHD Graphics">Intel UHD Graphics (Integrated)</option>
                                    <option value="AMD Radeon Vega Graphics">AMD Radeon Vega (Integrated)</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce GT Series">
                                    <option value="NVIDIA GeForce GT 710 1GB">GT 710 1GB</option>
                                    <option value="NVIDIA GeForce GT 730 2GB">GT 730 2GB</option>
                                    <option value="NVIDIA GeForce GT 1030 2GB">GT 1030 2GB GDDR5</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce GTX Series">
                                    <option value="NVIDIA GeForce GTX 1050 2GB">GTX 1050 2GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1050 Ti 4GB">GTX 1050 Ti 4GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1060 3GB">GTX 1060 3GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1060 6GB">GTX 1060 6GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1650 4GB">GTX 1650 4GB GDDR6</option>
                                    <option value="NVIDIA GeForce GTX 1660 6GB">GTX 1660 6GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1660 Super 6GB">GTX 1660 Super 6GB GDDR6</option>
                                    <option value="NVIDIA GeForce GTX 1660 Ti 6GB">GTX 1660 Ti 6GB GDDR6</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce RTX Series">
                                    <option value="NVIDIA GeForce RTX 2060 6GB">RTX 2060 6GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3050 8GB">RTX 3050 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3060 12GB">RTX 3060 12GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3060 Ti 8GB">RTX 3060 Ti 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3070 8GB">RTX 3070 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3080 10GB">RTX 3080 10GB GDDR6X</option>
                                    <option value="NVIDIA GeForce RTX 4060 8GB">RTX 4060 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 4060 Ti 8GB">RTX 4060 Ti 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 4070 12GB">RTX 4070 12GB GDDR6X</option>
                                </optgroup>
                                <optgroup label="AMD Radeon RX Series">
                                    <option value="AMD Radeon RX 550 4GB">RX 550 4GB</option>
                                    <option value="AMD Radeon RX 570 4GB">RX 570 4GB</option>
                                    <option value="AMD Radeon RX 580 8GB">RX 580 8GB</option>
                                    <option value="AMD Radeon RX 6500 XT 4GB">RX 6500 XT 4GB</option>
                                    <option value="AMD Radeon RX 6600 8GB">RX 6600 8GB</option>
                                    <option value="AMD Radeon RX 6650 XT 8GB">RX 6650 XT 8GB</option>
                                    <option value="AMD Radeon RX 6700 XT 12GB">RX 6700 XT 12GB</option>
                                    <option value="AMD Radeon RX 7600 8GB">RX 7600 8GB</option>
                                </optgroup>
                                <option value="Other">Other (specify in notes)</option>
                            </select>
                        </div>

                        {{-- MS Office --}}
                        <div>
                            <label class="spec-label">MS Office</label>
                            <select id="specOffice" class="form-input-gov">
                                <option value="">-- Select Office --</option>
                                <option value="None">None</option>
                                <option value="MS Office 2016">MS Office 2016</option>
                                <option value="MS Office 2019">MS Office 2019</option>
                                <option value="MS Office 2021">MS Office 2021</option>
                                <option value="Microsoft 365">Microsoft 365</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    {{-- Desktop-only accessories (hidden for Laptop) --}}
                    <div id="desktopAccessoriesSection" class="accessories-section">
                        <div class="section-uppercase">Desktop Accessories (optional)</div>
                        <div class="acc-grid-4">
                            <div>
                                <label class="spec-label">Form Factor</label>
                                <select id="specFormFactor" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Tower">Tower</option>
                                    <option value="Mini-PC">Mini-PC</option>
                                    <option value="All-in-One">All-in-One</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Monitor Included</label>
                                <select id="specMonitorIncluded" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Keyboard</label>
                                <select id="specKeyboard" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="None">None</option>
                                    <option value="Wired">Wired</option>
                                    <option value="Wireless">Wireless</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Mouse</label>
                                <select id="specMouse" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="None">None</option>
                                    <option value="Wired">Wired</option>
                                    <option value="Wireless">Wireless</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Laptop-only: battery condition --}}
                    <div id="laptopBatterySection" class="laptop-battery-section">
                        <div class="section-uppercase">Laptop Condition</div>
                        <div class="acc-grid-3">
                            <div>
                                <label class="spec-label">Battery Condition</label>
                                <select id="specBattery" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Needs Replacement">Needs Replacement</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MONITOR SPECS CONTAINER -->
                <div id="monitorSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Monitor Specifications</label>
                    <div class="acc-grid-2">
                        <div>
                            <label class="spec-label">Brand</label>
                            <select id="specMonitorBrand" class="form-input-gov">
                                <option value="">-- Select Brand --</option>
                                <option value="Dell">Dell</option>
                                <option value="HP">HP</option>
                                <option value="Lenovo">Lenovo</option>
                                <option value="Samsung">Samsung</option>
                                <option value="LG">LG</option>
                                <option value="Acer">Acer</option>
                                <option value="Asus">Asus</option>
                                <option value="AOC">AOC</option>
                                <option value="ViewSonic">ViewSonic</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="spec-label">Model</label>
                            <input type="text" id="specMonitorModel" class="form-input-gov" placeholder="P2422H">
                        </div>
                        <div>
                            <label class="spec-label">Screen Size</label>
                            <select id="specMonitorSize" class="form-input-gov">
                                <option value="">-- Select Size --</option>
                                <option value='19"'>19"</option>
                                <option value='21.5"'>21.5"</option>
                                <option value='22"'>22"</option>
                                <option value='24"'>24"</option>
                                <option value='27"'>27"</option>
                                <option value='32"'>32"</option>
                                <option value="Ultrawide">Ultrawide</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="spec-label">Resolution</label>
                            <select id="specMonitorResolution" class="form-input-gov">
                                <option value="">-- Select Resolution --</option>
                                <option value="1366x768 (HD)">1366x768 (HD)</option>
                                <option value="1920x1080 (FHD)">1920x1080 (FHD)</option>
                                <option value="2560x1440 (QHD)">2560x1440 (QHD)</option>
                                <option value="3840x2160 (4K UHD)">3840x2160 (4K UHD)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="spec-label">Additional Notes</label>
                            <input type="text" id="specMonitorNotes" class="form-input-gov" placeholder="IPS, 75Hz, HDMI+VGA...">
                        </div>
                    </div>
                </div>

                <!-- NETWORK/SERVER SPECS CONTAINER -->
                <div id="networkSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Network / Server Specifications</label>
                    <div class="mb-12">
                        <label class="spec-label">Device Type</label>
                        <select id="specNetworkDeviceType" class="form-input-gov">
                            <option value="">-- Select Type --</option>
                            <option value="Desktop">Desktop</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Server">Server</option>
                            <option value="Network Equipment">Network Equipment</option>
                            <option value="Firewall">Firewall</option>
                            <option value="Switch">Switch</option>
                            <option value="Router">Router</option>
                        </select>
                    </div>
                    {{-- Desktop/Laptop-type network device specs --}}
                    <div id="networkDesktopLaptopSpecs" class="net-grid">
                        <div>
                            <label class="spec-label">CPU</label>
                            <input type="text" id="specNetworkCpu" class="form-input-gov" placeholder="Xeon E5">
                        </div>
                        <div>
                            <label class="spec-label">RAM</label>
                            <input type="text" id="specNetworkRam" class="form-input-gov" placeholder="32GB ECC">
                        </div>
                        <div>
                            <label class="spec-label">Storage</label>
                            <input type="text" id="specNetworkStorage" class="form-input-gov" placeholder="2TB RAID">
                        </div>
                        <div>
                            <label class="spec-label">OS</label>
                            <input type="text" id="specNetworkOs" class="form-input-gov" placeholder="Windows Server 2022">
                        </div>
                    </div>
                    {{-- Equipment-type specs --}}
                    <div id="networkEquipmentSpecs" class="d-none">
                        <div class="equip-grid">
                            <div>
                                <label class="spec-label">Brand</label>
                                <input type="text" id="specNetworkBrand" class="form-input-gov" placeholder="Cisco, Fortinet...">
                            </div>
                            <div>
                                <label class="spec-label">Model</label>
                                <input type="text" id="specNetworkModel" class="form-input-gov" placeholder="ASA 5505">
                            </div>
                        </div>
                        <div>
                            <label class="spec-label">Specifications / Notes</label>
                            <textarea id="specNetworkEquipmentSpecs" class="form-input-gov textarea-xs" placeholder="24-port, PoE, managed..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label class="form-label-gov">Serial Number</label>
                        <input type="text" id="assetSerialNumber" placeholder="SN-XXXX-XXXX" class="form-input-gov font-mono">
                    </div>
                    <div>
                        <label class="form-label-gov">Device Status</label>
                        <select id="assetStatus" required class="form-input-gov">
                            <option value="Active">Active / In Use</option>
                            <option value="Spare">Spare / Stock</option>
                            <option value="Defective">Defective</option>
                            <option value="For Repair">For Repair</option>
                            {{-- Scrapped/For Disposal are set ONLY by the repair/disposal workflow, not manually --}}
                        </select>
                    </div>
                </div>

                @if(false)
                {{-- ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ
                     SUPER ADMIN: Division/Office Selection
                     ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ --}}
                <div class="mb-20">
                    <label class="form-label-gov flex-center-gap label-color-muted">
                        <i class="fa-solid fa-map-location-dot icon-section"></i>
                        Division/Office Location <span class="text-red-required">*</span>
                    </label>
                    <select id="assetOffice" required class="form-input-gov border-default">
                        <option value="">-- Select Division/Office --</option>
                        @foreach(['Central Office'] as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                    <p class="info-text">
                        <i class="fa-solid fa-circle-info info-icon-blue"></i>
                        Specify the division/office where this asset is physically deployed.
                    </p>
                </div>
                @else
                {{-- STANDARD ADMIN: Assign To Personnel --}}
                <div class="mb-20">
                    <label class="form-label-gov">Assign to Personnel</label>
                    <p class="info-text-sm">Personnel list is filtered to your office scope.</p>
                    <select id="assetAssignedUser" class="form-input-gov">
                        <option value="">-- Not Assigned (Available in Stock) --</option>
                    </select>
                </div>
                @endif


                <div id="generalSpecsGroup">
                    <!-- IT Parts / Components Quick-Fill -->
                    <div id="itPartsSection" class="it-parts-box">
                        <div class="it-parts-title">
                            <i class="fa-solid fa-screwdriver-wrench"></i> IT Part / Component Details
                        </div>
                        <div class="it-parts-grid">
                            <div>
                                <label class="it-part-label">Part Type</label>
                                <select id="itPartType" class="form-input-gov">
                                    <option value="">-- Select Part Type --</option>
                                    <option value="RAM">RAM (Memory)</option>
                                    <option value="SSD">SSD (Solid State Drive)</option>
                                    <option value="HDD">HDD (Hard Disk Drive)</option>
                                    <option value="GPU">GPU (Graphics Card)</option>
                                    <option value="CPU">CPU (Processor)</option>
                                    <option value="PSU">PSU (Power Supply Unit)</option>
                                    <option value="Motherboard">Motherboard</option>
                                    <option value="Battery">Battery (Laptop)</option>
                                    <option value="Cooling Fan">Cooling Fan</option>
                                    <option value="Network Card">Network Card / Wi-Fi Adapter</option>
                                    <option value="Keyboard">Keyboard (Replacement)</option>
                                    <option value="Other Part">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="it-part-label">Capacity / Speed / Size</label>
                                <input type="text" id="itPartSpec" class="form-input-gov" placeholder="e.g. 8GB DDR4, 512GB NVMe, RTX 3050" oninput="itPartTypeChange()">
                            </div>
                        </div>
                    </div>
                    <label class="form-label-gov">Other Details / Remarks</label>
                    <textarea id="generalSpecifications" class="form-input-gov textarea-md" placeholder="Additional technical details..."></textarea>
                </div>

                {{-- ΓöÇΓöÇ NEW: Financial & Lifecycle Fields ΓöÇΓöÇ --}}
                <hr class="section-divider">
                <div class="lifecycle-title">
                    <i class="fa-solid fa-calendar-days icon-section"></i> Lifecycle & Financial
                </div>
                <div class="lifecycle-grid">
                    <div>
                        <label class="form-label-gov">Date Acquired</label>
                        <input type="date" id="assetDateAcquired" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Acquisition Cost (Γé▒)</label>
                        <input type="number" id="assetAcquisitionCost" step="0.01" min="0" placeholder="0.00" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Warranty Expiration</label>
                        <input type="date" id="assetWarrantyExpiration" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">End of Useful Life</label>
                        <input type="date" id="assetEndOfUsefulLife" class="form-input-gov">
                    </div>
                </div>
                <div class="lifecycle-grid">
                    <div>
                        <label class="form-label-gov">Brand</label>
                        <input type="text" id="assetBrandInput" placeholder="e.g., HP, Dell, Lenovo" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Model</label>
                        <input type="text" id="assetModelInput" placeholder="e.g., ProBook 440 G8" class="form-input-gov">
                    </div>
                </div>
                <div class="mb-16">
                    <label class="form-label-gov">Property Number</label>
                    <input type="text" id="assetPropertyNumber" placeholder="e.g. NCMB-ICT-2024-001" class="form-input-gov">
                </div>
                <div class="mb-16">
                    <label class="form-label-gov">Asset Notes</label>
                    <textarea id="assetNotes" class="form-input-gov textarea-sm" placeholder="Additional notes about this asset..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-modern close-asset-btn btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn-save">Save Asset Record</button>
            </div>
        </form>
</x-modal>
