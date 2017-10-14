<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 

class Embargos extends TableGateway
{
    private $id;
    private $idemp;
    private $comen;
    private $tipo;
    private $estado;
    private $formula;
    private $valor;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_embargos', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->idemp    = $datos["idEmp"];   
        $this->comen    = $datos["comen"];  
        $this->tipo     = $datos["tipo"];  
        $this->estado   = $datos["estado"];  
        $this->formula  = $datos["formula"];  
        //$this->valor    = str_replace( array(",",".") , "", $datos["numero"] );  
        $this->valor    = $datos["numero"] ;  
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
           'idTemb'    => $this->tipo, 
           'estado'    => $this->estado, 
           'fecDoc'    => $dt['fecSis'],
           'formula'    => $data['formula'],
           'valor'     => $this->valor,
           'fecApr'    => $fecha,           
           'idTer'    => $data['idTer'],
           'idBan'    => $data['idBanco'],
           'idForP'    => $data['formaPago'],
           'numCuenta'  => $data['numCuenta'],
        );

       if ($id==0) // Nuevo registro
       {
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;          
       }          
       else // Mdificar registro
       {
          $this->update($datos, array('id' => $id));
          return $id; 
       }
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
