<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use Illuminate\Http\Request;

class UomController extends Controller
{
    public function index()
    {
        return response()->json(UnitOfMeasure::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:50|unique:units_of_measure',
            'symbol'  => 'required|string|max:20|unique:units_of_measure',
            'is_base' => 'boolean',
            'status'  => 'in:active,inactive',
        ]);
        return response()->json(UnitOfMeasure::create($data), 201);
    }

    public function show(UnitOfMeasure $uom)
    {
        return response()->json($uom->load('conversionsFrom.toUom', 'conversionsTo.fromUom'));
    }

    public function update(Request $request, UnitOfMeasure $uom)
    {
        $data = $request->validate([
            'name'    => 'sometimes|required|string|max:50|unique:units_of_measure,name,' . $uom->id,
            'symbol'  => 'sometimes|required|string|max:20|unique:units_of_measure,symbol,' . $uom->id,
            'is_base' => 'boolean',
            'status'  => 'in:active,inactive',
        ]);
        $uom->update($data);
        return response()->json($uom);
    }

    public function destroy(UnitOfMeasure $uom)
    {
        $uom->delete();
        return response()->json(['message' => 'UoM dihapus.']);
    }

    public function conversions(UnitOfMeasure $uom)
    {
        return response()->json($uom->load('conversionsFrom.toUom'));
    }

    public function storeConversion(Request $request)
    {
        $data = $request->validate([
            'from_uom_id' => 'required|exists:units_of_measure,id',
            'to_uom_id'   => 'required|exists:units_of_measure,id|different:from_uom_id',
            'factor'      => 'required|numeric|min:0.000001',
        ]);
        $conv = UomConversion::updateOrCreate(
            ['from_uom_id' => $data['from_uom_id'], 'to_uom_id' => $data['to_uom_id']],
            ['factor' => $data['factor']]
        );
        return response()->json($conv, 201);
    }

    public function destroyConversion(UomConversion $conversion)
    {
        $conversion->delete();
        return response()->json(['message' => 'Konversi dihapus.']);
    }
}