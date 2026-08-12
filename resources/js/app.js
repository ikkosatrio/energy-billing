import './bootstrap';

/**
 * ActionSelectHandler - Handle action dropdown selections in DataTables
 * Supports: view-detail, edit, delete, approve, reject actions
 */
class ActionSelectHandler {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('action-select')) {
                this.handleAction(e.target);
            }
        });
    }

    handleAction(select) {
        const action = select.value;
        const url = select.options[select.selectedIndex]?.dataset?.url;
        const id = select.dataset.id;

        if (!action || !url) {
            select.value = '';
            return;
        }

        switch (action) {
            case 'view-detail':
                this.viewDetail(url, id);
                break;
            case 'edit':
                this.edit(url, id);
                break;
            case 'delete':
                this.delete(url, id);
                break;
            case 'approve':
                this.approve(url, id);
                break;
            case 'reject':
                this.reject(url, id);
                break;
            default:
                break;
        }

        select.value = '';
    }

    viewDetail(url, id) {
        window.location.href = url;
    }

    edit(url, id) {
        window.location.href = url;
    }

    delete(url, id) {
        if (window.App?.Utils?.confirm) {
            window.App.Utils.confirm({
                title: 'Delete Cycle?',
                text: 'Are you sure you want to delete this cycle? This action cannot be undone.',
                icon: 'warning',
                confirmText: 'Yes, Delete',
                confirmColor: '#dc2626',
                onConfirm: () => {
                    this.sendRequest(url, 'DELETE');
                }
            });
        } else if (confirm('Are you sure you want to delete this cycle?')) {
            this.sendRequest(url, 'DELETE');
        }
    }

    approve(url, id) {
        if (window.App?.Utils?.custom) {
            window.App.Utils.custom({
                isConfirm: true,
                icon: null,
                title: 'Publish Cycle',
                html: '<div style="text-align: left; font-size: 14px; color: #475569; margin-bottom: 16px; margin-top: 4px;">Are you sure you want to approve this cycle schedule?</div>' +
                      '<div style="background-color: #fff1f2; border: 1px solid #fecaca; border-radius: 14px; padding: 14px 16px; text-align: left;">' +
                        '<span style="background-color: #fef08a; color: #854d0e; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; display: inline-block; margin-bottom: 8px;">WARNING</span>' +
                        '<div style="color: #ef4444; font-size: 13.5px; font-weight: 600; line-height: 1.4;">This action will update the cycle schedule data.</div>' +
                      '</div>',
                confirmText: 'Yes, Publish',
                cancelText: 'No, Cancel',
                didOpen: () => {
                    var actions = typeof Swal !== 'undefined' && Swal.getActions();
                    if (actions) {
                        actions.style.display = 'flex';
                        actions.style.justifyContent = 'flex-end';
                        actions.style.gap = '12px';
                        actions.style.width = '100%';
                        actions.style.marginTop = '24px';
                        actions.style.padding = '0';
                    }
                },
                onConfirm: () => {
                    this.sendRequest(url, 'POST');
                }
            });
        } else if (confirm('Are you sure you want to approve this cycle schedule?')) {
            this.sendRequest(url, 'POST');
        }
    }

    reject(url, id) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Reject/Revise Cycle',
                text: 'Masukkan alasan penolakan/revisi:',
                input: 'textarea',
                inputPlaceholder: 'Tulis catatan di sini...',
                showCancelButton: true,
                confirmButtonText: 'Submit Reject',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    this.sendRequestWithBody(url, 'POST', { RejectNote: result.value });
                }
            });
        } else {
            const note = prompt('Masukkan alasan penolakan/revisi:');
            if (note) {
                this.sendRequestWithBody(url, 'POST', { RejectNote: note });
            }
        }
    }

    sendRequest(url, method = 'POST') {
        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.code === 200) {
                alert(data.message || 'Action completed successfully');
                location.reload();
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
        });
    }

    sendRequestWithBody(url, method = 'POST', bodyData = {}) {
        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(bodyData),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.code === 200) {
                alert(data.message || 'Action completed successfully');
                location.reload();
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
        });
    }
}

// Initialize handler when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ActionSelectHandler();
    });
} else {
    new ActionSelectHandler();
}
