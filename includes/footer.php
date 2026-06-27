    <div class="footer">
        &copy; <?php echo date('Y'); ?> Electric Bikes ERP System. All rights reserved.
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function () {
    $('table.table:has(thead):not([data-skip-dt])').DataTable({
        pageLength: 25,
        language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
});
</script>
</body>
</html>
