// Version Update Utility
// Run this in browser console to update all version numbers

function updateVersionNumbers(newVersion) {
    if (!newVersion) {
        newVersion = '1.0.' + Date.now();
    }
    
    console.log('Updating version numbers to:', newVersion);
    
    // List of files that need version updates
    const filesToUpdate = [
        'index.html',
        'admin.html', 
        'profile.html',
        'book.html',
        'notifications.html',
        'auth.html'
    ];
    
    console.log('Files that need manual version updates:', filesToUpdate);
    console.log('Replace all occurrences of "?v=1.0.1" with "?v=' + newVersion + '"');
    
    return newVersion;
}

// Auto-update function for development
function autoUpdateVersions() {
    const timestamp = Math.floor(Date.now() / 1000); // Unix timestamp
    return updateVersionNumbers('1.0.' + timestamp);
}

console.log('Version utility loaded. Use updateVersionNumbers("1.0.2") or autoUpdateVersions()');

// Export for global use
window.updateVersionNumbers = updateVersionNumbers;
window.autoUpdateVersions = autoUpdateVersions;