/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2013 Certun, LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

Ext.define('App.controller.patient.LabOrders', {
	extend: 'Ext.app.Controller',
	requires: [
		'App.view.patient.windows.UploadDocument'
	],
	refs: [
		{
			ref: 'LabOrdersGrid',
			selector: 'patientlaborderspanel'
		},
		{
			ref: 'ElectronicLabOrderBtn',
			selector: 'patientlaborderspanel #electronicLabOrderBtn'
		},
		{
			ref: 'NewLabOrderBtn',
			selector: 'patientlaborderspanel #newLabOrderBtn'
		},
		{
			ref: 'PrintLabOrderBtn',
			selector: 'patientlaborderspanel #printLabOrderBtn'
		}
	],

	init: function(){
		var me = this;
		me.control({
			'patientlaborderspanel': {
				activate: me.onLabOrdersGridActive,
				selectionchange: me.onLabOrdersGridSelectionChange,
				beforerender: me.onLabOrdersGridBeforeRender
			},
			'#rxLabOrderLabsLiveSearch': {
				select: me.onLoincSearchSelect
			},
			'patientlaborderspanel #electronicLabOrderBtn': {
				click: me.onElectronicLabOrderBtnClick
			},
			'patientlaborderspanel #newLabOrderBtn': {
				click: me.onNewLabOrderBtnClick
			},
			'patientlaborderspanel #printLabOrderBtn': {
				click: me.onPrintLabOrderBtnClick
			}
		});
	},

	onLabOrdersGridBeforeRender: function(grid){
		app.on('patientunset', function(){
			grid.editingPlugin.cancelEdit();
			grid.getStore().removeAll();
		});
	},

	onLabOrdersGridSelectionChange: function(sm, selected){
		this.getPrintLabOrderBtn().setDisabled(selected.length == 0);
	},

	onLoincSearchSelect: function(cmb, records){
		var form = cmb.up('form').getForm();

		say(form.getRecord());

		form.getRecord().set({code: records[0].data.loinc_number});
		form.findField('code').setValue(records[0].data.loinc_number);
		form.findField('note').focus(false, 200);
	},

	onElectronicLabOrderBtnClick: function(){
		say('TODO!');
	},

	onNewLabOrderBtnClick: function(){
		var me = this,
			grid = me.getLabOrdersGrid(),
			store = grid.getStore();

		grid.editingPlugin.cancelEdit();
		store.insert(0, {
			pid: app.patient.pid,
			eid: app.patient.eid,
			uid: app.user.id,
			date_ordered: new Date(),
			order_type: 'lab',
			status: 'Pending',
			priority: 'Normal'
		});
		grid.editingPlugin.startEdit(0, 0);
	},

	onPrintLabOrderBtnClick: function(){
		var me = this,
			grid = me.getLabOrdersGrid(),
			items = grid.getSelectionModel().getSelection(),
			params = {},
			data,
			i;

		params.pid = app.patient.pid;
		params.eid = app.patient.eid;
		params.orderItems = [ ];
		params.docType = 'Lab';

		params.templateId = 4;
		params.orderItems.push(['Description', 'Notes']);
		for(i = 0; i < items.length; i++){
			data = items[i].data;

			params.orderItems.push([
					data.description + ' [' + data.code_type + ':' + data.code + ']',
				data.note
			]);
		}

		DocumentHandler.createTempDocument(params, function(provider, response){
			if(window.dual){
				dual.onDocumentView(response.result.id, 'Lab');
			}else{
				app.onDocumentView(response.result.id, 'Lab');
			}
		});
	},

	onLabOrdersGridActive:function(grid){
		var store = grid.getStore();

		if(!grid.editingPlugin.editing){
			store.clearFilter(true);
			store.filter([
				{
					property: 'pid',
					value: app.patient.pid
				},
				{
					property: 'order_type',
					value: 'lab'
				}
			]);
		}
	},

	labOrdersGridStatusColumnRenderer:function(v){
		var color = 'black';

		if(v == 'Canceled'){
			color = 'red';
		}else if(v == 'Pending'){
			color = 'orange';
		}else if(v == 'Routed'){
			color = 'blue';
		}else if(v == 'Complete'){
			color = 'green';
		}

		return '<div style="color:' + color + '">' + v + '</div>';
	}


});