<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc;

class Auto extends TableGateway
{
    private $id;
    private $idcon;
    private $valor;
    private $idccos;
    private $horasc;
    private $ccosemp;
    private $horcal;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_emp_conc', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->idcon    = $datos["tipo"];   
        $this->valor   = $datos["numero"];
        $this->idccos  = $datos["idCencos"];
        $this->horasc  = $datos["horasC"];
        $this->horcal  = $datos["check1"];        
        $this->ccosemp = $datos["check2"];    
    }
    
   
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $f = new LogFunc($this->adapter);
       $dt = $f->getDatLog();
       $datos=array
       (
           'idEmp'  =>$this->id,
           'idCon'  =>$this->idcon,
           'idCon'    => $this->idcon,
           'idCcos'   => $this->idccos,
           'horasCal' => $this->horcal,
           'cCosEmp'  => $this->ccosemp,           
           'fechaI'  => $data['fechaIni'],                      
           'fechaF'  => $data['fechaFin'],                      
           'idUsu'   => $dt['idUsu'],                      
           'valor'    => $this->valor,
        );
        $this->insert($datos);
        $inserted_id = $this->lastInsertValue;  
        return $inserted_id;

    }
   
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
        // Se borra primero los tipos de nomina que afecta
        $result=$this->adapter->query("delete from n_emp_conc_tn where idEmCon=".$id,Adapter::QUERY_MODE_EXECUTE);          
        $this->delete(array('id' => $id));               
     }
}
?>
