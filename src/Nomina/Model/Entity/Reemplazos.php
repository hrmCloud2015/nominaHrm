<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 

class Reemplazos extends TableGateway
{
    private $id;
    private $idemp;
    private $comen;
    private $idEmp2;
    private $fechaini;
    private $fechafin;
    private $estado;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_reemplazos', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->idemp   = $datos["idEmp"];   
        $this->comen   = $datos["comen"];  
        $this->idEmp2  = $datos["idEmp2"];  
        $this->fechaini  = $datos["fechaIni"];  
        $this->fechafin  = $datos["fechaFin"];  
        $this->estado   = $datos["estado"];  
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();
       // ---        
       $fecha = '';
       if ($this->estado==1)
       {       
           $fecha = $dt['fecSis'];
       } 
       $datos=array
       (
           'idEmp'     => $this->idemp,
           'comen'     => $this->comen,    
           'idEmpR'    => $this->idEmp2, 
           'fecApr'    => $fecha,
           'fecDoc'    => $dt['fecSis'],
           'fechai'    => $this->fechaini, 
           'fechaf'    => $this->fechafin,
           'idSal'     => $data['idSalario'],
           'estado'    => 1,   
       );           
       if ($id==0) // Nuevo registro
          $this->insert($datos);
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
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
       $this->delete(array('id' => $id));               
     }    
     
}
?>
