/**
 * OCNHS Clinic System - Offline Sync Handler
 * This script handles saving data to IndexedDB when offline and 
 * automatically syncing it once the connection is restored.
 */

const DB_NAME = 'ClinicOfflineDB';
const DB_VERSION = 1;
const STORE_NAME = 'outputQueue';

let db;

// Initialize IndexedDB
const initDB = () => {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };

        request.onsuccess = (e) => {
            db = e.target.result;
            resolve(db);
        };

        request.onerror = (e) => reject(e.target.error);
    });
};

const readFileAsBase64 = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
};

const dataURLtoFile = (dataurl, filename) => {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while(n--){
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, {type:mime});
};

// Save record to sync queue
const saveToSyncQueue = async (url, formData, recordType) => {
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (value instanceof File && value.size > 0) {
            data[key] = {
                isFile: true,
                name: value.name,
                type: value.type,
                data: await readFileAsBase64(value)
            };
        } else {
            data[key] = value;
        }
    }

    const entry = {
        url,
        data,
        recordType,
        timestamp: new Date().getTime()
    };

    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const store = transaction.objectStore(STORE_NAME);
    store.add(entry);

    console.log(`Saved ${recordType} to offline queue.`);
    updateSyncStatus();
    showOfflineToast();
};

// UI Feedback
const showOfflineToast = () => {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        title: 'Offline Mode',
        text: 'Server connection lost. Your data will be saved locally on this device and automatically uploaded once the connection is restored.',
        icon: 'warning',
        confirmButtonColor: '#00ACB1'
    });
};

const updateSyncStatus = () => {
    if (!db) return;
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const request = store.count();

    request.onsuccess = () => {
        const count = request.result;
        let indicator = document.getElementById('offlineSyncIndicator');
        
        if (count > 0) {
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'offlineSyncIndicator';
                indicator.style = 'position: fixed; top: 10px; right: 80px; background: #f39c12; color: white; padding: 6px 15px; border-radius: 30px; font-size: 11px; font-weight: bold; z-index: 10000; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 8px; cursor: pointer; border: 1px solid rgba(255,255,255,0.2);';
                indicator.onclick = syncData;
                document.body.appendChild(indicator);
            }
            indicator.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> ${count} PENDING SYNC`;
            indicator.style.display = 'flex';
        } else if (indicator) {
            indicator.style.display = 'none';
        }
    };
};

const updateNetworkStatusUI = () => {
    const statusDot = document.getElementById('statusDot');
    if (!statusDot) return;

    if (navigator.onLine) {
        statusDot.style.background = '#2ecc71';
        statusDot.title = 'Online';
    } else {
        statusDot.style.background = '#e74c3c';
        statusDot.title = 'Offline';
    }
};

// Sync everything in the queue to the server
const syncData = async () => {
    if (!navigator.onLine || !db) return;

    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const request = store.getAll();

    request.onsuccess = async () => {
        const items = request.result;
        if (items.length === 0) return;

        console.log(`Connection restored. Syncing ${items.length} records...`);

        // Use a loading toast
        Swal.fire({
            title: 'Auto-Syncing...',
            text: `Uploading ${items.length} offline record(s) to the server. Please wait...`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let successCount = 0;
        for (const item of items) {
            try {
                const formData = new FormData();
                for (const key in item.data) {
                    const val = item.data[key];
                    if (val && typeof val === 'object' && val.isFile && val.data) {
                        formData.append(key, dataURLtoFile(val.data, val.name));
                    } else {
                        formData.append(key, val);
                    }
                }

                const response = await fetch(item.url, {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    // We check for success in JSON or just response.ok
                    const deleteTx = db.transaction([STORE_NAME], 'readwrite');
                    deleteTx.objectStore(STORE_NAME).delete(item.id);
                    successCount++;
                }
            } catch (err) {
                console.error(`Failed to sync record ID ${item.id}`, err);
            }
        }
        
        Swal.close();
        updateSyncStatus();
        
        if (successCount > 0) {
            Swal.fire({
                title: 'Sync Complete!',
                text: `Successfully uploaded ${successCount} record(s) to the server.`,
                icon: 'success',
                timer: 3000,
                toast: true,
                position: 'top-end',
                showConfirmButton: false
            }).then(() => {
                // Refresh listing if we are on a table page
                const path = window.location.pathname;
                if (path.includes('student') || path.includes('employees') || path.includes('others')) {
                    window.location.reload();
                }
            });
        }
    };
};

// Global Listeners
window.addEventListener('online', () => {
    updateNetworkStatusUI();
    syncData();
});
window.addEventListener('offline', () => {
    updateNetworkStatusUI();
    updateSyncStatus();
});

// Initialize on load
initDB().then(() => {
    console.log('Offline DB Ready.');
    updateSyncStatus();
    updateNetworkStatusUI();
    if (navigator.onLine) syncData();
});

// Export to window
window.ClinicSync = {
    save: saveToSyncQueue,
    sync: syncData,
    updateStatus: updateSyncStatus
};
