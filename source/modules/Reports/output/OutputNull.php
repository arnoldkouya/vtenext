<?php

/* crmv@97862 */

class ReportOutputNull extends ReportOutputBase {
	
	public function addHeader($headerField) {
		return false;
	}
	
	public function addFooter($footerField) {
		return false;
	}
	
	public function addRow($row) {
		return false;
	}
	
	public function addCell($cell) {
		return;
	}
		
	public function getHeaderForCell($cell) {
		return false;
	}
	
}