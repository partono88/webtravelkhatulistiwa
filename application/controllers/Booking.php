<?php
defined('BASEPATH') or exit('No Direct Script Access Allowed');
date_default_timezone_set('Asia/Jakarta');
class Booking extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        cek_login();
        $this->load->model(['ModelBooking', 'ModelUser']);
    }
    public function index()
    {
        $id = ['bo.id_user' => $this->uri->segment(3)];
        $id_user = $this->session->userdata('id_user');
        $data['booking'] = $this->ModelBooking->joinOrder($id)->result();
        $user = $this->ModelUser->cekData(['email' => $this->session->userdata('email')])->row_array();
        foreach ($user as $a) {
            $data = [
                'image' => $user['image'],
                'user' => $user['nama'],
                'email' => $user['email'],
                'tanggal_input' => $user['tanggal_input']
            ];
        }
        $dtb = $this->ModelBooking->showtemp(['id_user' => $id_user])->num_rows();
        if ($dtb < 1) {
            $this->session->set_flashdata('pesan', '<div class="alert alert-massege alert-danger" role="alert">Tidak Ada Buku dikeranjang</div>');
            redirect(base_url());
        } else {
            $data['temp'] = $this->db->query("select image, armada, penulis, panitia, jadwal_keberangkatan,id_tiket from temp where id_user='$id_user'")->result_array();
        }
        $data['armada'] = "Data Booking";
        $this->load->view('templates/templates-user/header', $data);
        $this->load->view('booking/data-booking', $data);
        $this->load->view('templates/templates-user/modal');
        $this->load->view('templates/templates-user/footer');
    }
    public function tambahBooking()
    {
        $id_tiket = $this->uri->segment(3);
        //memilih data buku yang untuk dimasukkan ke tabel temp/keranjang melaluivariabel $isi 
        $d = $this->db->query("Select*from tiket where id='$id_tiket'")->row();
        //berupa data2 yang akan disimpan ke dalam tabel temp/keranjang 
        $isi = [
            'id_tiket' => $id_tiket,
            'armada' => $d->armada,
            'id_user' => $this->session->userdata('id_user'),
            'email_user' => $this->session->userdata('email'),
            'tgl_booking' => date('Y-m-d H:i:s'),
            'image' => $d->image,
            'penulis' => $d->tujuan_wisata,
            'panitia' => $d->panitia,
            'jadwal_keberangkatan' => $d->jadwal_keberangkatan
        ];
        //cek apakah buku yang di klik booking sudah ada di keranjang
        $temp = $this->ModelBooking->getDataWhere('temp', ['id_tiket' => $id_tiket])->num_rows();
        $userid = $this->session->userdata('id_user');
        //cek jika sudah memasukan 3 buku untuk dibooking dalam keranjang
        $tempuser = $this->db->query("select*from temp where id_user ='$userid'")->num_rows();
        //cek jika masih ada booking buku yang belum diambil
        $databooking = $this->db->query("select*from booking where id_user='$userid'")->num_rows();
        if ($databooking > 0) {
            $this->session->set_flashdata('pesan', '<div class="alert alert-danger alert-message" role="alert">Masih Ada booking buku sebelumnya yang belum diambil.<br> Abmil Buku yang dibooking atau tunggu 1x24 Jam untuk bisa booking kembali </div>');
            redirect(base_url());
        }
        //jika buku yang diklik booking sudah ada di keranjang
        if ($temp > 0) {
            $this->session->set_flashdata('pesan', '<div class="alert alert-danger alert-message" role="alert">Buku ini Sudah anda booking </div>');
            redirect(base_url() . 'home');
        }
        //jika buku yang akan dibooking sudah mencapai 3 item
        if ($tempuser == 3) {
            $this->session->set_flashdata('pesan', '<div class="alert alert-danger alert-message" role="alert">Booking Buku Tidak Boleh Lebih dari 3</div>');
            redirect(base_url() . 'home');
        }
        //membuat tabel temp jika belum ada
        $this->ModelBooking->createTemp();
        $this->ModelBooking->insertData('temp', $isi);
        //pesan ketika berhasil memasukkan buku ke keranjang
        $this->session->set_flashdata('pesan', '<div class="alert alert-success alert-message" role="alert">Buku berhasil ditambahkan ke keranjang </div>');
        redirect(base_url() . 'home');
    }
    public function hapusbooking()
    {
        $id_tiket = $this->uri->segment(3);
        $id_user = $this->session->userdata('id_user');

        $this->ModelBooking->deleteData(['id_tiket' => $id_tiket], 'temp');
        $kosong = $this->db->query("select*from temp where id_user='$id_user'")->num_rows();
        if ($kosong < 1) {
            $this->session->set_flashdata('pesan', '<div class="alert alert-massege alert-danger" role="alert">Tidak Ada Buku dikeranjang</div>');
            redirect(base_url());
        } else {
            redirect(base_url() . 'booking');
        }
    }
    public function bookingSelesai($where)
    {
        //mengupdate stok dan dibooking di tabel buku saat proses booking diselesaikan
        $this->db->query("UPDATE tiket, temp SET tiket.dibooking=tiket.dibooking+1, tiket.stok=tiket.stok-1 WHERE tiket.id=temp.id_tiket");
        $tglsekarang = date('Y-m-d');
        $isibooking = [
            'id_booking' => $this->ModelBooking->kodeOtomatis('booking', 'id_booking'),
            'tgl_booking' => date('Y-m-d H:m:s'),
            'batas_ambil' => date('Y-m-d', strtotime('+2 days', strtotime($tglsekarang))),
            'id_user' => $where
        ];
        //menyimpan ke tabel booking dan detail booking, dan mengosongkan tabel temporari
        $this->ModelBooking->insertData('booking', $isibooking);
        $this->ModelBooking->simpanDetail($where);
        $this->ModelBooking->kosongkanData('temp');
        redirect(base_url() . 'booking/info');
    }
    public function info()
    {
        $where = $this->session->userdata('id_user');
        $data['user'] = $this->session->userdata('nama');
        $data['armada'] = "Selesai Booking";
        $data['useraktif'] = $this->ModelUser->cekData(['id' => $this->session->userdata('id_user')])->result();
        $data['items'] = $this->db->query("select*from booking bo, booking_detail d, tiket bu where d.id_booking=bo.id_booking and d.id_tiket=bu.id and bo.id_user='$where'")->result_array();
        $this->load->view('templates/templates-user/header', $data);
        $this->load->view('booking/info-booking', $data);
        $this->load->view('templates/templates-user/modal');
        $this->load->view('templates/templates-user/footer');
    }
    public function exportToPdf()
    {
        $id_user = $this->session->userdata('id_user');
        $data['user'] = $this->session->userdata('nama');
        $data['armada'] = "Cetak Bukti Booking";
        $data['useraktif'] = $this->ModelUser->cekData(['id' => $this->session->userdata('id_user')])->result();
        $data['items'] = $this->db->query("select*from booking bo, booking_detail d, tiket bu where d.id_booking=bo.id_booking and d.id_tiket=bu.id and bo.id_user='$id_user'")->result_array();
        $this->load->library('pdf');
        $paper_size = 'A4'; // ukuran kertas
        $orientation = 'landscape'; //tipe format kertas potrait atau landscape
        $this->pdf->set_paper($paper_size, $orientation);
        //Convert to PDF
        $this->pdf->filename = "bukti-booking-$id_user.pdf";
        $this->pdf->load_view('booking/bukti-pdf', $data);
        // nama file pdf yang di hasilkan
    }
}