<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\FinanceImport;
use App\Models\FinanceCategory;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class FinanceController extends Controller {
 private function organizationId(Request $r): int {
    $user=$r->user();
    if($user->role!=='super_admin') return (int)$user->organization_id;
    return (int)($r->header('X-Organization-Id') ?: $r->input('organization_id'));
 }
 public function summary(Request $r){
    $org=$this->organizationId($r); abort_if(!$org,422,'Select an organisation first.'); $q=FinanceTransaction::where('organization_id',$org);
  $income=(clone $q)->where('type','income')->sum('amount');$expense=(clone $q)->where('type','expense')->sum('amount');
  $byCategory=(clone $q)->where('type','expense')->select('category',DB::raw('SUM(amount) total'))->groupBy('category')->orderByDesc('total')->get();
  $monthly=(clone $q)->select(DB::raw("DATE_FORMAT(transaction_date,'%Y-%m') month"),DB::raw("SUM(CASE WHEN type='income' THEN amount ELSE 0 END) income"),DB::raw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) expense"))->groupBy('month')->orderBy('month')->get();
   $paymentScope=Payment::whereHas('package.publication.report.project',fn($query)=>$query->where('organization_id',$org));
   $payments=(clone $paymentScope)->where('status','paid')->with('package.publication')->latest('paid_at')->get();
   $paymentByMethod=$payments->groupBy(fn($payment)=>$payment->method.'_'.$payment->currency)->map(fn($items)=>['method'=>$items->first()->method,'currency'=>$items->first()->currency,'count'=>$items->count(),'total'=>(float)$items->sum('amount')]);
   $paymentByCurrency=$payments->groupBy('currency')->map(fn($items)=>['currency'=>$items->first()->currency,'count'=>$items->count(),'total'=>(float)$items->sum('amount')])->values();
   return ['income'=>(float)$income,'expense'=>(float)$expense,'balance'=>(float)($income-$expense),'by_category'=>$byCategory,'monthly'=>$monthly,'payment_revenue'=>['paid_count'=>$payments->count(),'by_currency'=>$paymentByCurrency,'pending_count'=>(clone $paymentScope)->where('status','pending')->count(),'rejected_count'=>(clone $paymentScope)->where('status','rejected')->count(),'by_method'=>$paymentByMethod,'recent'=>$payments->take(20)->values()]];
 }
 public function transactions(Request $r){
    $org=$this->organizationId($r); abort_if(!$org,422,'Select an organisation first.');
    $q=FinanceTransaction::where('organization_id',$org)->latest('transaction_date');
    if($r->filled('q')){$term=$r->string('q');$q->where(fn($x)=>$x->where('description','like',"%{$term}%")->orWhere('category','like',"%{$term}%")->orWhere('reference','like',"%{$term}%"));}
    return $q->paginate(50);
 }
 public function categories(Request $r){
    $org=$this->organizationId($r); abort_if(!$org,422,'Select an organisation first.');
    return FinanceCategory::where('organization_id',$org)->orderBy('type')->orderBy('name')->get();
 }
 public function storeCategory(Request $r){
    $org=$this->organizationId($r); abort_if(!$org,422,'Select an organisation first.');
    $d=$r->validate(['name'=>'required|string|max:100','type'=>'required|in:income,expense']);
    return response()->json(FinanceCategory::create(['organization_id'=>$org,...$d]),201);
 }
 public function destroyCategory(Request $r, FinanceCategory $category){
    abort_unless((int)$category->organization_id===$this->organizationId($r),404);
    $category->delete(); return response()->noContent();
 }
 public function import(Request $r){
    $org=$this->organizationId($r);abort_if(!$org,422,'Select an organisation first.');
    $d=$r->validate(['file'=>'required|file|mimes:csv,txt|max:10240']);
    $imp=FinanceImport::create(['organization_id'=>$org,'source'=>'quickbooks','uploaded_by'=>$r->user()->id,'status'=>'pending']);
  $handle=fopen($d['file']->getRealPath(),'r');$header=fgetcsv($handle);$count=0;
  while(($row=fgetcsv($handle))!==false){$m=array_combine($header,$row);if(!$m)continue;$amount=(float)($m['Amount']??$m['amount']??0);$type=strtolower($m['Type']??$m['type']??'expense');if(!in_array($type,['income','expense','transfer']))$type='expense';FinanceTransaction::create(['organization_id'=>$org,'finance_import_id'=>$imp->id,'transaction_date'=>$m['Date']??$m['date']??now()->toDateString(),'type'=>$type,'account'=>$m['Account']??$m['account']??null,'category'=>$m['Category']??$m['category']??null,'project_code'=>$m['Project']??$m['project']??null,'reference'=>$m['Reference']??$m['reference']??null,'description'=>$m['Description']??$m['description']??null,'amount'=>$amount,'currency'=>$m['Currency']??'UGX']);$count++;}
  fclose($handle);$imp->update(['rows_imported'=>$count,'status'=>'processed']);return ['import'=>$imp];
 }
 public function export(Request $r){
      $org=$this->organizationId($r);abort_if(!$org,422,'Select an organisation first.');
      $rows=FinanceTransaction::where('organization_id',$org)->orderBy('transaction_date')->get();
      return response()->streamDownload(function()use($rows){
         $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Transaction date','Type','Account','Category','Project code','Reference','Description','Amount','Currency']);
         foreach($rows as $row)fputcsv($out,[$row->transaction_date?->format('Y-m-d'),$row->type,$row->account,$row->category,$row->project_code,$row->reference,$row->description,$row->amount,$row->currency]);
         fclose($out);
      },'finance-transactions.csv',['Content-Type'=>'text/csv']);
 }
}