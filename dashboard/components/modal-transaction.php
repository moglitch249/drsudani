<?php
/**
 * مودال إضافة معاملة مالية — مشترك
 */
?>
<div class="modal-overlay" id="transactionModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">إضافة معاملة مالية</h3>
            <button class="modal-close" id="closeModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="transactionForm" novalidate enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" id="modalCsrf" value="">
            <input type="hidden" name="wp_user_id" id="modalUserId" value="">

            <div class="modal-user-info" id="modalUserInfo" style="display:none;">
                <div class="mu-avatar" id="modalUserAvatar"></div>
                <div class="mu-details">
                    <span class="mu-name" id="modalUserName"></span>
                    <span class="mu-email" id="modalUserEmail"></span>
                </div>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="txAmount">المبلغ <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="number" id="txAmount" name="amount" class="form-control"
                                   placeholder="0.00" step="0.01" min="0.01" required>
                            <span class="input-suffix">ر.س</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="txType">نوع المعاملة <span class="required">*</span></label>
                        <select id="txType" name="type" class="form-control" required>
                            <option value="">— اختر النوع —</option>
                            <option value="deposit">إيداع</option>
                            <option value="withdraw">سحب</option>
                            <option value="adjustment">تعديل</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="txMethod">طريقة الدفع <span class="required">*</span></label>
                    <select id="txMethod" name="payment_method" class="form-control" required>
                        <option value="">— اختر الطريقة —</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                        <option value="wallet">محفظة إلكترونية</option>
                        <option value="cash">نقدي</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="txNotes">ملاحظات</label>
                    <textarea id="txNotes" name="notes" class="form-control"
                              rows="3" placeholder="أي ملاحظات إضافية..."></textarea>
                </div>

                <div class="form-group">
                    <label for="txReceipt">صورة الإيصال</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="txReceipt" name="receipt_image"
                               accept="image/jpeg,image/png,image/webp" hidden>
                        <div class="file-upload-placeholder" id="fileUploadPlaceholder">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <span>اضغط لاختيار الصورة أو اسحبها هنا</span>
                            <small>JPG, PNG, WebP — الحد الأقصى 2 ميجابايت</small>
                        </div>
                        <img id="receiptPreview" class="receipt-preview" src="" alt="معاينة الإيصال" style="display:none;">
                    </div>
                </div>

                <div class="alert alert-error" id="modalError" style="display:none;"></div>
                <div class="alert alert-success" id="modalSuccess" style="display:none;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" id="cancelModal">إلغاء</button>
                <button type="submit" class="btn btn-primary" id="submitTransaction">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    حفظ المعاملة
                </button>
            </div>
        </form>
    </div>
</div>
